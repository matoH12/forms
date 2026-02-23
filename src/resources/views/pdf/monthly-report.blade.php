<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Mesačný report - {{ $month }}</title>
    <style>
        * {
            font-family: DejaVu Sans, sans-serif;
        }
        body {
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0066b3;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0066b3;
            margin: 0 0 5px 0;
            font-size: 22px;
        }
        .header p {
            margin: 3px 0;
            color: #666;
            font-size: 10px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background: #0066b3;
            color: white;
            padding: 8px 12px;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #fafafa;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .stat-box {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #0066b3;
        }
        .stat-label {
            font-size: 10px;
            color: #666;
        }
        .approved { color: #16a34a; }
        .rejected { color: #dc2626; }
        .pending { color: #f59e0b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #999;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mesačný report aktivity</h1>
        <p><strong>Obdobie:</strong> {{ $month }}</p>
        <p><strong>Vygenerované:</strong> {{ $generatedAt }} | <strong>Vygeneroval:</strong> {{ $generatedBy }}</p>
    </div>

    <!-- Summary Stats -->
    <div class="section">
        <div class="section-title">Súhrnné štatistiky</div>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">{{ $monthlyStats->total ?? 0 }}</div>
                <div class="stat-label">Celkom odpovedí</div>
            </div>
            <div class="stat-box">
                <div class="stat-value approved">{{ $monthlyStats->approved ?? 0 }}</div>
                <div class="stat-label">Schválených</div>
            </div>
            <div class="stat-box">
                <div class="stat-value rejected">{{ $monthlyStats->rejected ?? 0 }}</div>
                <div class="stat-label">Zamietnutých</div>
            </div>
            <div class="stat-box">
                <div class="stat-value pending">{{ $monthlyStats->pending ?? 0 }}</div>
                <div class="stat-label">Čakajúcich</div>
            </div>
        </div>
    </div>

    <!-- Submissions by Form -->
    <div class="section">
        <div class="section-title">Odpovede podľa formulárov</div>
        @if($submissionsByForm->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Formulár</th>
                    <th class="text-center">Celkom</th>
                    <th class="text-center">Schválené</th>
                    <th class="text-center">Zamietnuté</th>
                    <th class="text-center">Čakajúce</th>
                </tr>
            </thead>
            <tbody>
                @foreach($submissionsByForm as $form)
                <tr>
                    <td>{{ $form->form_name }}</td>
                    <td class="text-center">{{ $form->total }}</td>
                    <td class="text-center approved">{{ $form->approved }}</td>
                    <td class="text-center rejected">{{ $form->rejected }}</td>
                    <td class="text-center pending">{{ $form->pending }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>Žiadne odpovede v tomto období.</p>
        @endif
    </div>

    <!-- Reviewers Summary -->
    <div class="section">
        <div class="section-title">Aktivita schvaľovateľov</div>
        @if($reviewersSummary->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Schvaľovateľ</th>
                    <th>Email</th>
                    <th class="text-center">Schválil</th>
                    <th class="text-center">Zamietol</th>
                    <th class="text-center">Celkom</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reviewersSummary as $reviewer)
                <tr>
                    <td>{{ $reviewer->first_name && $reviewer->last_name ? $reviewer->first_name . ' ' . $reviewer->last_name : $reviewer->name }}</td>
                    <td>{{ $reviewer->email }}</td>
                    <td class="text-center approved">{{ $reviewer->approved_count }}</td>
                    <td class="text-center rejected">{{ $reviewer->rejected_count }}</td>
                    <td class="text-center"><strong>{{ $reviewer->total_reviewed }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>Žiadna aktivita schvaľovateľov v tomto období.</p>
        @endif
    </div>

    @if($approvalActivity->count() > 0)
    <div class="page-break"></div>

    <!-- Detailed Approval Activity -->
    <div class="section">
        <div class="section-title">Detail schvaľovacej aktivity</div>
        <table>
            <thead>
                <tr>
                    <th>Dátum</th>
                    <th>Formulár</th>
                    <th>Žiadateľ</th>
                    <th>Stav</th>
                    <th>Schválil</th>
                </tr>
            </thead>
            <tbody>
                @foreach($approvalActivity as $submission)
                <tr>
                    <td>{{ $submission->reviewed_at ? $submission->reviewed_at->format('d.m.Y H:i') : '-' }}</td>
                    <td>{{ $submission->localized_form_name }}</td>
                    <td>{{ $submission->user?->name ?? 'Anonymný' }}</td>
                    <td class="{{ $submission->status === 'approved' ? 'approved' : 'rejected' }}">
                        {{ $submission->status === 'approved' ? 'Schválené' : 'Zamietnuté' }}
                    </td>
                    <td>{{ $submission->reviewer?->first_name && $submission->reviewer?->last_name ? $submission->reviewer->first_name . ' ' . $submission->reviewer->last_name : $submission->reviewer?->name ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Vygenerované systémom Formuláre | {{ $generatedAt }}
    </div>
</body>
</html>
