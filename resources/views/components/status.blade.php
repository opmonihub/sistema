@php
$map = ['aprovado' => 'success', 'autorizado' => 'success', 'rejeitado' => 'danger', 'cancelado' => 'danger', 'contingencia' => 'warning', 'pendente' => 'warning', 'rascunho' => 'default'];
$status = strtolower($status ?? '');
$tone = $tone ?? ($map[$status] ?? 'info');
@endphp
<span class="label label-{{ $tone }} status-{{ $status }}">{{ $slot }}</span>
