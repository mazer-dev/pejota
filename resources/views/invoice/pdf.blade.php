<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->title }}</title>
    <style>
        * {
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            box-sizing: border-box;
        }

        h1, h2, h3, h4, h5, h6, p, span, div {
            font-family: DejaVu Sans;
            font-size: 10px;
            font-weight: normal;
        }

        th, td {
            font-family: DejaVu Sans;
            font-size: 10px;
        }

        .panel {
            margin-bottom: 20px;
            background-color: #fff;
            border: 1px solid transparent;
            border-radius: 4px;
            -webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, .05);
            box-shadow: 0 1px 1px rgba(0, 0, 0, .05);
        }

        .panel-default {
            border-color: #ddd;
        }

        .panel-body {
            padding: 15px;
        }

        table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 0px;
            border-spacing: 0;
            border-collapse: collapse;
            background-color: transparent;

        }

        thead {
            text-align: left;
            display: table-header-group;
            vertical-align: middle;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 6px;
        }

        .well {
            min-height: 20px;
            padding: 19px;
            margin-bottom: 20px;
            background-color: #f5f5f5;
            border: 1px solid #e3e3e3;
            border-radius: 4px;
            -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .05);
            box-shadow: inset 0 1px 1px rgba(0, 0, 0, .05);
        }
    </style>
    @if($invoice->duplicate_header)
        <style>
            @page {
                margin-top: 140px;
            }

            header {
                top: -100px;
                position: fixed;
            }
        </style>
    @endif
</head>
<body>
@php
    $media = $invoice->company->getFirstMedia();
    $data = file_get_contents($media->getPath(), false);
    $img_base_64 = base64_encode($data);
    $path_img = 'data:image/' . $media->getTypeFromMime() . ';base64,' . $img_base_64;
@endphp

<header>
    <div style="position:absolute; right:0;">
        <img class="img-rounded" style="height: 50pt;" src="{{ $path_img }}">
    </div>
    <div style="margin-left:0pt;">
        <span style="font-size: 1.5rem; font-weight: bold; ">@lang('Invoice')</span><br/>
        @if ($invoice->number)
            <span style="font-size: 1rem;">
                <b>#</b>{{ $invoice->number }}
            </span>
            <br/>
            <br/>
        @endif

        <b>@lang('Date'): </b> {{ $invoice->created_at->format(\App\Helpers\PejotaHelper::getUserDateFormat()) }}
        @if ($invoice->due_date)
            <span style="position:absolute; right: 0;">
                <b>@lang('Due date'): </b>{{ $invoice->due_date->format(\App\Helpers\PejotaHelper::getUserDateFormat()) }}<br/>
            </span>
        @endif
    </div>
</header>
<main>
    <div style="clear:both; position:relative;">
        <div style="position:absolute; left:0pt; width:250pt;">
            <h4>Business Details:</h4>
            <div class="panel panel-default">
                <div class="panel-body">
                    {{ $invoice->company->name }}<br/>
                    {{ $invoice->company->phone ?? '' }}<br/>
                    {{ $invoice->company->email ?? '' }}<br/>
                    {{ $invoice->company->website ?? '' }}<br/>
                </div>
            </div>
        </div>
        <div style="margin-left: 300pt;">
            <h4>Customer Details:</h4>
            <div class="panel panel-default">
                <div class="panel-body">
                    {{ $invoice->client->name }} | #{{ $invoice->client->id }}<br/>
                    {{ $invoice->client->phone ?? '' }}<br/>
                    {{ $invoice->client->email ?? '' }}<br/>
                </div>
            </div>
        </div>
    </div>

    @if($invoice->extra_info)
        <h4>@lang('Extra Information:')</h4>
        <div class="panel panel-default">
            <div class="panel-body">
                {{ $invoice->extra_info }}
            </div>
        </div>
    @endif

    <h4>Items:</h4>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th>#</th>
            <th>@lang('Name')</th>
            <th style="text-align: right">@lang('Quantity')</th>
            <th>@lang('Unit')</th>
            <th style="text-align: right">@lang('Price')</th>
            <th style="text-align: right">@lang('Discount')</th>
            <th style="text-align: right">@lang('Total')</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->name }}</td>
                <td style="text-align: right">{{ $item->quantity }}</td>
                <td>{{ $item->unit->name }}</td>
                <td style="text-align: right">{{ $item->price }}</td>
                <td style="text-align: right">{{ $invoice->discount }}</td>
                <td style="text-align: right">{{ $item->total }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="clear:both; position:relative;">
        @if($invoice->notes)
            <div style="position:absolute; left:0pt; width:250pt;">
                <h4>Notes:</h4>
                <div class="panel panel-default">
                    <div class="panel-body">
                        {{ $invoice->notes }}
                    </div>
                </div>
            </div>
        @endif
        <div style="margin-left: 300pt;">
            <h4>Total:</h4>
            <table class="table table-bordered">
                <tbody>
                @if($invoice->dicount)
                    <tr>
                        <td><b>Discount</b></td>
                        <td>{{ $invoice->dicount }}</td>
                    </tr>
                @endif
                <tr>
                    <td><b>TOTAL</b></td>
                    <td style="text-align: right"><b>{{ $invoice->total }}</b></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
    @if ($invoice->footnote)
        <br/><br/>
        <div class="well">
            {{ $invoice->footnote }}
        </div>
    @endif
</main>

<!-- Page count -->
<script type="text/php">
    if (isset($pdf) && $GLOBALS['with_pagination'] && $PAGE_COUNT > 1) {
        $pageText = "{PAGE_NUM} of {PAGE_COUNT}";
        $pdf->page_text(($pdf->get_width()/2) - (strlen($pageText) / 2), $pdf->get_height()-20, $pageText, $fontMetrics->get_font("DejaVu Sans, Arial, Helvetica, sans-serif", "normal"), 7, array(0,0,0));
    }
</script>
</body>
</html>
