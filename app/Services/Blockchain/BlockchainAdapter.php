<?php
namespace App\Services\Blockchain;
interface BlockchainAdapter {
 public function latestBlock(): int;
 public function transferEvents(int $fromBlock, int $toBlock, array $recipientAddresses): array;
 public function transactionReceipt(string $hash): array;
}
