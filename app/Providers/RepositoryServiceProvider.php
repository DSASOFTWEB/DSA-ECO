<?php

namespace App\Providers;

use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\Contracts\ContratoRepositoryInterface;
use App\Repositories\Contracts\MensalidadeRepositoryInterface;
use App\Repositories\Contracts\ProdutoRepositoryInterface;
use App\Repositories\Contracts\VendaRepositoryInterface;
use App\Repositories\Eloquent\ClienteRepository;
use App\Repositories\Eloquent\ContratoRepository;
use App\Repositories\Eloquent\MensalidadeRepository;
use App\Repositories\Eloquent\ProdutoRepository;
use App\Repositories\Eloquent\VendaRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Mapeamento interface => implementação. Services dependem sempre da
     * interface (nunca da classe Eloquent concreta), o que permite trocar
     * a fonte de dados (ex: cache, outra origem) sem tocar nos Services.
     */
    public array $bindings = [
        ClienteRepositoryInterface::class => ClienteRepository::class,
        ContratoRepositoryInterface::class => ContratoRepository::class,
        MensalidadeRepositoryInterface::class => MensalidadeRepository::class,
        ProdutoRepositoryInterface::class => ProdutoRepository::class,
        VendaRepositoryInterface::class => VendaRepository::class,
    ];
}
