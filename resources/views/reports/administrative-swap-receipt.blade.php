<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Registro administrativo {{ $receipt['reference'] }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#12333a;line-height:1.6}h1{font-size:19px}h2{font-size:12px;margin-top:22px}.notice{border:1px solid #c9dad7;padding:12px}p{overflow-wrap:break-word;white-space:pre-wrap}footer{margin-top:28px;font-size:9px}</style></head><body>
<h1>Retificada administrativamente</h1><p>{{ $receipt['reference'] }} — {{ $receipt['kind_label'] }}</p>
<p class="notice">{{ $receipt['effect'] }}</p>
<h2>Aprovação original preservada</h2><p>{{ $receipt['status_label'] }} — {{ $receipt['approved_at'] ?? 'Data legada não informada' }}</p>
<h2>Autor e registro</h2><p>{{ $receipt['author'] }} (ID {{ $receipt['author_id'] }}) — {{ $receipt['recorded_at'] }}</p>
<h2>Participantes e plantões</h2><p>{{ $receipt['requester'] }} / {{ $receipt['substitute'] }}
Plantão #{{ $receipt['shift_id'] }} @if($receipt['substitute_shift_id']) / Contrapartida #{{ $receipt['substitute_shift_id'] }}@endif</p>
<h2>Justificativa</h2><p>{{ $receipt['reason'] }}</p>
<h2>Evidências e referências</h2><p>{{ $receipt['evidence_notes'] }}</p>
<footer>Operação {{ $receipt['operation_id'] }}. Este documento registra a declaração administrativa; não comprova restauração de atribuições ou alteração da cota. Aprovações e comprovantes anteriores permanecem preservados.</footer>
</body></html>
