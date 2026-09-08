<?php

namespace App\Providers;

use App\Policies\AuditoriaPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;

/**
 * As demais Policies (Cliente, Contrato, Mensalidade, Produto, Venda, Caixa,
 * User, Unidade, Carteirinha, Plano, Comissao, Dependente) são resolvidas
 * automaticamente pela convenção de nomes do Laravel
 * (App\Models\Xyz -> App\Policies\XyzPolicy), então não precisam estar
 * listadas aqui. Só entram explicitamente casos que fogem da convenção.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // "Auditoria" não é um Eloquent Model próprio (usa-se o Activity do
        // spatie/laravel-activitylog), então a policy é ligada via Gate nomeado.
        Gate::define('auditoria.visualizar', [AuditoriaPolicy::class, 'viewAny']);

        // Check-in manual (PDV) de cliente com plano: mesma permissão do
        // terminal/totem de catraca ("acessos.validar"), só que operada por
        // uma pessoa buscando o cliente em vez de ler um QR Code.
        Gate::define('checkin.realizar', fn ($user) => $user->can('acessos.validar'));

        // Super-admin do SaaS (equipe da própria DSA) enxerga tudo, de qualquer empresa.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // O e-mail padrão do Laravel vem em inglês — todo o resto do
        // sistema é em português, então personaliza o conteúdo aqui em vez
        // de criar uma Notification própria só pra isso.
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject('Redefinição de senha — '.config('app.name'))
                ->greeting('Olá!')
                ->line('Recebemos um pedido de redefinição de senha para a sua conta.')
                ->action('Redefinir senha', $url)
                ->line('Este link expira em 60 minutos.')
                ->line('Se você não pediu a redefinição de senha, nenhuma ação é necessária — pode ignorar este e-mail.');
        });
    }
}
