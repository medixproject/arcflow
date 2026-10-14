<?php
namespace App\Services\Blockchain;
class MockAdapter implements BlockchainAdapter {
 public function latestBlock(): int { return 100; }
 public function transferEvents(int $fromBlock,int $toBlock,array $recipientAddresses): array { return []; }
 public function transactionReceipt(string $hash): array { return ['status'=>'0x1','blockNumber'=>'0x64','transactionHash'=>$hash]; }
}
