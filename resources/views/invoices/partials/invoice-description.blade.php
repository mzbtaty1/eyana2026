حجز الرحلة {{ $invoice->es_id }}<br>

اسماء : 
@foreach($users as $user) 
    {{ $user->client_name }} / 
@endforeach
<br>

ارقام الحجز : 
@foreach($users as $user) 
    {{ $user->client_booking_id }} / 
@endforeach
<br>

ارقام التذاكر : 
@foreach($users as $user) 
    {{ $user->client_ticket_id }} / 
@endforeach
<br>

ارقام الجواز : 
@foreach($users as $user) 
    {{ $user->client_passport_id }} / 
@endforeach