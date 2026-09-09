@php
    // Calculate row styling based on invoice type
    $rowClass = '';
    $rowStyle = '';
    
    if ($invoice->invoice_shared == 0) {
        $rowClass = '';
        $rowStyle = '';
    } else {
        $rowClass = 'invoice-row-shared';
        $rowStyle = 'color: white;';
    }
    
    $esIdPrefix = substr($invoice->es_id, 0, 6);
    if ($esIdPrefix == "FLY-RD") {
        $rowClass = 'invoice-row-fly-rd';
        $rowStyle = 'color: white;';
    } elseif ($esIdPrefix == "FLY-RS") {
        $rowClass = 'invoice-row-fly-rs';
        $rowStyle = 'color: white;';
    }

    // Get related data (Note: This should ideally be moved to controller with eager loading for better performance)
    $vendors = App\Models\TicketVendor::where('ticket_system_id', $invoice->ticket_system_id)->get();
    $beneficiaryInfo = App\Models\Supplier::find($invoice->invoice_beneficiaries);
    $users = App\Models\TicketUser::where('ticket_system_id', $invoice->ticket_system_id)->get();
    $memberInfo = App\Models\User::find($invoice->invoice_create_by);

    // Calculate totals
    $totalClientNetPrice = 0;
    $totalClientBoughtPrice = 0;
    foreach($users as $user) {
        $totalClientNetPrice += (float) $user->client_net_pice;
        $totalClientBoughtPrice += (float) $user->client_bought_price;
    }

    // Invoice types mapping
    $invoiceTypes = [
        1 => "فواتير الطيران",
        2 => "فواتير تأشيرات", 
        3 => "فواتير سياحه داخليه",
        4 => "فواتير سياحه خارجيه",
        5 => "فواتير سياحه دينيه",
        6 => "فواتير تأمينات السفر",
        7 => "فواتير تحاليل السفر",
        8 => "فواتير نقل سياحى",
    ];

    $invoiceType = $invoiceTypes[$invoice->invoice_section] ?? "";

    // Handle special calculations for FLY-RD type
    $costAmount = $totalClientNetPrice;
    $sellAmount = $totalClientBoughtPrice;
    $profitAmount = $totalClientBoughtPrice - $totalClientNetPrice;

    if ($esIdPrefix == "FLY-RD") {
        $mostarad = App\Models\AccountStatement::where('es_id', $invoice->es_id)
                                               ->where('credit_balance', 0)
                                               ->first();
        $mortaga = App\Models\AccountStatement::where('es_id', $invoice->es_id)
                                              ->where('debit_balance', 0)  
                                              ->first();
        
        $costAmount = (float)($mostarad->debit_balance ?? 0);
        $sellAmount = (float)($mortaga->credit_balance ?? 0);
        $profitAmount = $costAmount - $sellAmount;
    }
@endphp

<tr>
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        <a href="{{ asset($invoice->invoice_ticket_file) }}">
            {{ $invoice->es_id }}
        </a>
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $invoice->invoice_date }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $invoice->invoice_travel_date }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        @foreach($vendors as $vendor)
            @php
                $vendorInfo = App\Models\Supplier::find($vendor->vendor_id);
            @endphp
            {{ $vendorInfo->name }} /
        @endforeach
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $beneficiaryInfo->name ?? '' }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $costAmount }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $sellAmount }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $invoice->from_location }} - {{ $invoice->to_location }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        @foreach($users as $user)
            {{ $user->client_name }}<br>
        @endforeach
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        ارقام الحجز : 
        @foreach($users as $user) 
            {{ $user->client_booking_id }} / 
        @endforeach
        <br>
        ارقام التذاكر : 
        @foreach($users as $user) 
            {{ $user->client_ticket_id }} / 
        @endforeach
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $profitAmount }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $invoiceType }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        @include('invoices.partials.invoice-status', [
            'invoice' => $invoice,
            'totalClientBoughtPrice' => $totalClientBoughtPrice,
            'rowClass' => $rowClass,
            'rowStyle' => $rowStyle
        ])
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        {{ $memberInfo->name ?? '' }}
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        @include('invoices.partials.invoice-description', [
            'invoice' => $invoice,
            'users' => $users
        ])
    </td>
    
    <td class="{{ $rowClass }}" style="{{ $rowStyle }}">
        @include('invoices.partials.invoice-actions', [
            'invoice' => $invoice,
            'totalClientBoughtPrice' => $totalClientBoughtPrice
        ])
    </td>
</tr>