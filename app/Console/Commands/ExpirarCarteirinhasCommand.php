<?php

namespace App\Console\Commands;

use App\Models\Carteirinha;
use Illuminate\Console\Command;

class ExpirarCarteirinhasCommand extends Command
{
    protected $signature = 'carteirinhas:expirar';

    protected $description = 'Marca como "expirada" toda carteirinha ativa cuja data de expiração já passou';

    public function handle(): int
    {
        $total = Carteirinha::where('status', 'ativa')
            ->whereNotNull('expira_em')
            ->whereDate('expira_em', '<', now()->toDateString())
            ->update(['status' => 'expirada']);

        $this->info("Carteirinhas expiradas: {$total}");

        return self::SUCCESS;
    }
}
