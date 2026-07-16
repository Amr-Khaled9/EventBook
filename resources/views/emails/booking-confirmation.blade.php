<!DOCTYPE html>
<html>
<body>
    <h2>Booking Confirmed!</h2>
    <p>Your booking has been created successfully.</p>

    <ul>
        <li><strong>Booking ID:</strong> {{ $booking->id }}</li>
        <li><strong>Train:</strong> {{ $train->number }} ({{ $train->from_station }} → {{ $train->to_station }})</li>
        <li><strong>Date:</strong> {{ $trip->trip_date->format('Y-m-d') }}</li>
        <li><strong>Departure:</strong> {{ $train->departure_time }}</li>
        <li><strong>Seats:</strong> {{ $booking->seats_count }}</li>
        <li><strong>Status:</strong> {{ $booking->status }}</li>
    </ul>
</body>
</html>