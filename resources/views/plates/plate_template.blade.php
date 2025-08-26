@php
    $provinceLogos = [
        'Punjab' => '#007bff',
        'Sindh' => '#ffc107',
        'KPK' => '#17a2b8',
        'Balochistan' => '#dc3545',
        "Gilgit"=>"Orange",
        "IC"=>"Pink",
         "Kashmir"=>"Brown",
    ];
    $provinceColor = $provinceLogos[$plate->region] ?? null;
@endphp





<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
    style="width: 385px; height:185px; margin: auto; border-collapse: collapse;">
    <tr>
        <td
            style="border: 1px solid #0d6efd; background-color: {{ $provinceColor }}; border-radius: 8px; padding: 15px; box-shadow: 0 8px 16px rgba(0,0,0,0.15); font-family: Arial, sans-serif; text-align: center;">

            <!-- Province Logo -->
            <img src="{{ $provinceLogo }}" alt="Province Logo" width="40" height="40"
                style="display: block; margin: 0 auto 10px auto;">

            <!-- Region -->
            <div style="font-weight: bold; font-size: 1rem;  margin-bottom: 5px;">
                @if ($plate->featured != 1)
                    {{ $plate->regionRelation->full_form }}
                @else
                    {{ $plate->regionRelation->urdu_name }}
                @endif
            </div>

            <!-- Plate Number -->
            <div
                style="font-size: 2rem; letter-spacing: 5px; color: white; font-weight: bold;  padding: 6px 10px; border-radius: 6px; display: inline-block; margin-bottom: 8px;">
                {{ $plate->plate_number ?? 'ABC 000' }}
            </div>

            <!-- City -->
            <div style=" font-size: 0.9rem; margin-top: 6px;">
                @if ($plate->featured != 1)
                    {{ strtoupper($plate->city) }}
                @else
                    {{ $plate->cityRelation->name_ur }}
                @endif
            </div>

        </td>
    </tr>
</table>
