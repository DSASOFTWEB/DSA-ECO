<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Lista única de permissões do sistema. Mantida centralizada aqui para
     * que Policies e views (@can) sempre encontrem uma permissão existente
     * — evita o erro clássico de "permission does not exist" em produção.
     */
    protected array $permissoes = [
        'clientes.visualizar', 'clientes.criar', 'clientes.editar', 'clientes.excluir',
        'planos.visualizar', 'planos.criar', 'planos.editar', 'planos.excluir',
        'contratos.visualizar', 'contratos.criar', 'contratos.editar', 'contratos.cancelar',
        'financeiro.visualizar', 'financeiro.baixar_manual', 'financeiro.cancelar_mensalidade', 'financeiro.enviar_cobranca',
        'contas_pagar.visualizar', 'contas_pagar.gerenciar',
        'contas_receber.visualizar', 'contas_receber.gerenciar',
        'caixa.visualizar', 'caixa.abrir', 'caixa.fechar', 'caixa.movimentar',
        'caixa.transferir', 'caixa.estornar', 'caixa.editar_movimentacao',
        'estoque.visualizar', 'estoque.criar', 'estoque.editar', 'estoque.excluir', 'estoque.ajustar',
        'vendas.visualizar', 'vendas.criar', 'vendas.cancelar',
        'food.visualizar', 'food.operar', 'food.fechar', 'food.configurar',
        'pousada.visualizar', 'pousada.gerenciar', 'pousada.reservar', 'pousada.checkin', 'pousada.checkout', 'pousada.consumos', 'pousada.limpeza', 'pousada.comodato',
        'tipos_entrada.visualizar', 'tipos_entrada.gerenciar',
        'comissoes.visualizar', 'comissoes.visualizar_proprias', 'comissoes.pagar',
        'carteirinhas.visualizar', 'carteirinhas.emitir', 'carteirinhas.bloquear',
        'acessos.validar', 'acessos.cortesia',
        'empresa.gerenciar',
        'unidades.visualizar', 'unidades.criar', 'unidades.editar',
        'terminais.visualizar', 'terminais.criar', 'terminais.editar',
        'usuarios.visualizar', 'usuarios.criar', 'usuarios.editar', 'usuarios.excluir',
        'auditoria.visualizar',
    ];

    public function run(): void
    {
        foreach ($this->permissoes as $permissao) {
            Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
        }

        // super_admin: enxerga tudo via Gate::before (AuthServiceProvider), não precisa de permissões explícitas.
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->syncPermissions($this->permissoes);

        Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web'])
            ->syncPermissions(array_filter($this->permissoes, fn ($p) => ! str_starts_with($p, 'usuarios.excluir')));

        Role::firstOrCreate(['name' => 'recepcao', 'guard_name' => 'web'])
            ->syncPermissions([
                'clientes.visualizar', 'clientes.criar', 'clientes.editar',
                'contratos.visualizar', 'contratos.criar',
                'financeiro.visualizar', 'financeiro.baixar_manual', 'financeiro.enviar_cobranca',
                'caixa.visualizar', 'caixa.abrir', 'caixa.fechar', 'caixa.movimentar',
                'carteirinhas.visualizar', 'carteirinhas.emitir', 'carteirinhas.bloquear',
                'acessos.validar', 'acessos.cortesia',
                'vendas.visualizar', 'vendas.criar',
                'food.visualizar', 'food.operar', 'food.fechar',
                'pousada.visualizar', 'pousada.reservar', 'pousada.checkin', 'pousada.checkout', 'pousada.consumos', 'pousada.limpeza', 'pousada.comodato',
                'estoque.visualizar',
            ]);

        Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web'])
            ->syncPermissions([
                'clientes.visualizar', 'clientes.criar',
                'contratos.visualizar', 'contratos.criar',
                'vendas.visualizar', 'vendas.criar',
                'food.visualizar', 'food.operar',
                'comissoes.visualizar_proprias',
                'caixa.visualizar',
                'estoque.visualizar',
            ]);

        Role::firstOrCreate(['name' => 'financeiro', 'guard_name' => 'web'])
            ->syncPermissions([
                'financeiro.visualizar', 'financeiro.baixar_manual', 'financeiro.cancelar_mensalidade', 'financeiro.enviar_cobranca',
                'contas_pagar.visualizar', 'contas_pagar.gerenciar',
                'contas_receber.visualizar', 'contas_receber.gerenciar',
                'caixa.visualizar', 'caixa.fechar',
                'caixa.transferir', 'caixa.estornar', 'caixa.editar_movimentacao',
                'comissoes.visualizar', 'comissoes.pagar',
                'contratos.visualizar',
                'clientes.visualizar',
                'auditoria.visualizar',
            ]);

        // Após recriar permissões/papéis, o cache do Spatie (se existir)
        // pode negar checagens válidas e virar 403 em telas como /usuarios/{id}/edit.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
