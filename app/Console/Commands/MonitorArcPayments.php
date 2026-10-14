<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceAddressAllocation;
use App\Services\Blockchain\ArcRpcAdapter;
use App\Services\Blockchain\MockAdapter;
use App\Services\ExactTokenAmount;
use App\Services\LatePaymentRecorder;
use App\Services\LegacyTransferReviewRecorder;
use App\Services\PaymentMatcher;
use Illuminate\Console\Command;
use RuntimeException;

class MonitorArcPayments extends Command
{
    protected $signature = 'arcflow:monitor {--once : Run one reconciliation pass}';
    protected $description = 'Monitor Arc USDC transfer events and reconcile invoices.';
    private const CURSOR_KEY = 'arcflow:last_block';

    protected function adapter(): object
    {
        return config('arc.mode') === 'mock'
            ? new MockAdapter()
            : new ArcRpcAdapter(config('arc.rpc_url'), strtolower(config('arc.usdc_contract')), config('arc.transfer_topic'));
    }

    public function handle(PaymentMatcher $matcher, ExactTokenAmount $amountConverter, LatePaymentRecorder $lateRecorder, LegacyTransferReviewRecorder $legacyRecorder): int
    {
        $network = (string) config('arc.network');

        // Keep active legacy invoices monitored, and permanently monitor every allocated HD address
        // so transfers after expiry/cancellation can be captured for manual review.
        // Include all historical invoice addresses. Expired legacy invoices
        // must remain discoverable for manual late-transfer reconciliation.
        $activeAddresses = Invoice::query()
            ->where('network', $network)
            ->pluck('payment_address');
        $allocatedAddresses = InvoiceAddressAllocation::query()->where('network', $network)->pluck('address');
        $addresses = $activeAddresses->concat($allocatedAddresses)
            ->filter(fn ($address) => is_string($address) && $address !== '')
            ->map(fn ($address) => strtolower($address))->unique()->values()->all();

        $configuredStart = config('arc.start_block');
        $lastProcessed = cache()->get(self::CURSOR_KEY);

        if ($lastProcessed === null) {
            $startValue = is_string($configuredStart) || is_int($configuredStart)
                ? ltrim((string) $configuredStart, '0')
                : '';

            if ($startValue === '') {
                $startValue = '0';
            }

            $validStart = preg_match('/^[0-9]+$/D', (string) $configuredStart) === 1
                && filter_var($startValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false;

            if (!$validStart) {
                $this->error('Initial scan blocked: set ARC_START_BLOCK to a verified starting block before monitoring.');
                return self::FAILURE;
            }
        }

        if (!$addresses) {
            $this->info('No active invoice or allocated payment addresses configured.');
            return self::SUCCESS;
        }

        $adapter = $this->adapter();
        if (config('arc.mode') !== 'mock') {
            $expectedChainId = config('arc.chain_id');
            if (!is_string($expectedChainId) && !is_int($expectedChainId)) {
                $this->error('Arc RPC scan blocked: ARC_CHAIN_ID must be configured as a decimal chain ID.');
                return self::FAILURE;
            }
            $expectedChainId = (string) $expectedChainId;
            if (!preg_match('/^[0-9]+$/D', $expectedChainId) || (int) $expectedChainId < 1) {
                $this->error('Arc RPC scan blocked: ARC_CHAIN_ID must be configured as a decimal chain ID.');
                return self::FAILURE;
            }
            $actualChainId = $adapter->chainId();
            if ($actualChainId !== (int) $expectedChainId) {
                $this->error("Arc RPC scan blocked: expected chain ID {$expectedChainId}, received {$actualChainId}.");
                return self::FAILURE;
            }
        }

        $latest = (int) $adapter->latestBlock();
        $start = $lastProcessed !== null
            ? ((int) $lastProcessed + 1)
            : (int) $configuredStart;
        if ($start > $latest) $start = $latest;

        $matched = 0;
        $lateReview = 0;
        $events = $adapter->transferEvents($start, $latest, $addresses);

        foreach ($events as $event) {
            $topics = $event['topics'] ?? [];
            if (count($topics) < 3 || empty($event['transactionHash'])) continue;
            $from = '0x' . substr($topics[1], -40);
            $to = '0x' . substr($topics[2], -40);
            $raw = ltrim((string) ($event['data'] ?? '0x0'), '0x');
            $raw = str_pad($raw, 64, '0', STR_PAD_LEFT);
            $decimals = (int) ($event['_arc_decimals'] ?? 6);
            $amount = $amountConverter->toSixDecimals($this->hexToDecimal($raw), $decimals);
            // Zero-value or sub-0.000001 dust transfers carry no payment and must not alter invoices.
            if ($amount === '0.000000') continue;
            $block = hexdec($event['blockNumber'] ?? '0x0');
            $token = $event['_arc_token_contract'] ?? config('arc.usdc_contract');
            $txHash = (string) $event['transactionHash'];

            if (!isset($event['logIndex'])) {
                throw new RuntimeException('RPC transfer event is missing logIndex; refusing to checkpoint this scan.');
            }
            $logIndex = hexdec((string) $event['logIndex']);

            $payment = $matcher->matchTransfer(
                $network, $token, $from, $to, $amount, $block, $txHash, $latest, $logIndex
            );
            if ($payment) {
                $matched++;
                continue;
            }
            $late = $lateRecorder->recordIfAllocated($network, $token, $from, $to, $amount, $block, $txHash, $latest, $logIndex);
            if ($late) {
                $lateReview++;
                continue;
            }
            $legacyReview = $legacyRecorder->recordIfLegacyAddress(
                $network, $token, $from, $to, $amount, $block,
                $txHash, $latest, $logIndex
            );
            if ($legacyReview) $lateReview++;
        }

        $confirmed = $matcher->confirmPendingPayments($network, $latest)->count();
        // Do not advance the cursor until every event and confirmation pass succeeds.
        cache()->put(self::CURSOR_KEY, $latest);
        $this->info("Processed blocks {$start}-{$latest}; matched {$matched}; late-review {$lateReview}; newly confirmed {$confirmed}.");
        return self::SUCCESS;
    }

    private function hexToDecimal(string $hex): string
    {
        $hex = preg_replace('/^0x/i', '', trim($hex)) ?? '';
        $hex = strtolower($hex);
        if ($hex === '') return '0';
        if (!preg_match('/^[0-9a-f]+$/', $hex)) throw new \InvalidArgumentException('Invalid hexadecimal token amount.');

        $decimal = '0';
        foreach (str_split($hex) as $character) {
            $carry = hexdec($character);
            $out = '';
            for ($i = strlen($decimal) - 1; $i >= 0; $i--) {
                $value = ((int) $decimal[$i] * 16) + $carry;
                $out = ($value % 10) . $out;
                $carry = intdiv($value, 10);
            }
            while ($carry > 0) {
                $out = ($carry % 10) . $out;
                $carry = intdiv($carry, 10);
            }
            $decimal = ltrim($out, '0') ?: '0';
        }
        return $decimal;
    }
}
