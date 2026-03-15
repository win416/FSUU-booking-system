<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
SessionManager::requireLogin();

$user = SessionManager::getUser();
$db = getDB();

// Get available services
$services = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY service_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - FSUU Dental Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css">
    <style>
        #calendar {
            max-width: 1100px;
            margin: 0 auto;
        }
        .fc-event {
            cursor: pointer;
        }
        .time-slot {
            display: inline-block;
            margin: 5px;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
        }
        .time-slot:hover {
            background-color: #e9ecef;
        }
        .time-slot.selected {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .time-slot.disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            border-color: #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Navigation (same as dashboard) -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">FSUU Dental Clinic</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="book-appointment.php">
                            <i class="bi bi-calendar-plus"></i> Book Appointment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-appointments.php">
                            <i class="bi bi-calendar-check"></i> My Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="bi bi-clock-history"></i> History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <h2>Book an Appointment</h2>
        
        <div class="row">
            <!-- Step 1: Select Service -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Step 1: Select Service</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php while($service = $services->fetch_assoc()): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card service-card <?php echo isset($_GET['service']) && $_GET['service'] == $service['service_id'] ? 'border-primary' : ''; ?>" 
                                     data-service-id="<?php echo $service['service_id']; ?>"
                                     style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <h5 class="card-title"><?php echo $service['service_name']; ?></h5>
                                        <p class="card-text text-muted"><?php echo $service['description']; ?></p>
                                        <p class="card-text"><small>Duration: <?php echo $service['duration_minutes']; ?> mins</small></p>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Select Date & Time -->
            <div class="col-md-12 mb-4" id="dateTimeStep" style="display: none;">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Step 2: Select Date & Time</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div id="calendar"></div>
                            </div>
                            <div class="col-md-4">
                                <div id="timeSlots" class="mt-3">
                                    <h6>Available Time Slots</h6>
                                    <div id="timeSlotsContainer" class="mt-2">
                                        <p class="text-muted">Select a date first</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Medical Information & Consent -->
            <div class="col-md-12 mb-4" id="consentStep" style="display: none;">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Step 3: Medical Information & Consent</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get user's medical info
                        $medical = $db->prepare("SELECT * FROM medical_info WHERE user_id = ?");
                        $medical->bind_param("i", $user['user_id']);
                        $medical->execute();
                        $medical_info = $medical->get_result()->fetch_assoc();
                        ?>
                        
                        <form id="bookingForm">
                            <div class="mb-3">
                                <label class="form-label">Do you have any allergies?</label>
                                <textarea class="form-control" name="allergies" rows="2"><?php echo $medical_info['allergies'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Medical Conditions (if any)</label>
                                <textarea class="form-control" name="medical_conditions" rows="2"><?php echo $medical_info['medical_conditions'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Medications</label>
                                <textarea class="form-control" name="medications" rows="2"><?php echo $medical_info['medications'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name" 
                                       value="<?php echo $medical_info['emergency_contact_name'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Emergency Contact Number</label>
                                <input type="text" class="form-control" name="emergency_contact_number" 
                                       value="<?php echo $medical_info['emergency_contact_number'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consent" id="consent" required>
                                    <label class="form-check-label" for="consent">
                                        I have read and agree to the clinic policies and consent to the dental procedure.
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Additional Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Confirm Booking</button>
                            <button type="button" class="btn btn-secondary" onclick="goBack()">Back</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
    <script>
    let selectedService = null;
    let selectedDate = null;
    let selectedTime = null;
    let calendar = null;

    // Service selection
    $('.service-card').click(function() {
        $('.service-card').removeClass('border-primary');
        $(this).addClass('border-primary');
        selectedService = $(this).data('service-id');
        
        $('#dateTimeStep').show();
        
        // Initialize calendar if not already done
        if (!calendar) {
            initCalendar();
        }
    });

    function initCalendar() {
        var calendarEl = document.getElementById('calendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth'
            },
            selectable: true,
            select: function(info) {
                selectedDate = info.startStr;
                loadTimeSlots(selectedDate);
            },
            // Disable past dates
            validRange: {
                start: new Date().toISOString().split('T')[0]
            },
            // Show blocked dates
            events: {
                url: '../api/get-blocked-dates.php',
                failure: function() {
                    alert('Error loading blocked dates');
                }
            },
            dateClick: function(info) {
                selectedDate = info.dateStr;
                loadTimeSlots(selectedDate);
            }
        });
        calendar.render();
    }

    function loadTimeSlots(date) {
        $.ajax({
            url: '../api/get-slots.php',
            method: 'GET',
            data: {
                date: date,
                service_id: selectedService
            },
            success: function(response) {
                displayTimeSlots(response.slots, response.maxPerDay);
            }
        });
    }

    function displayTimeSlots(slots, maxPerDay) {
        let html = '';
        const now = new Date();
        const selectedDate = new Date(selectedDate + 'T00:00:00');
        
        // Check if selected date is today
        const isToday = selectedDate.toDateString() === now.toDateString();
        
        slots.forEach(function(slot) {
            const [hours, minutes] = slot.time.split(':');
            const slotTime = new Date(selectedDate);
            slotTime.setHours(parseInt(hours), parseInt(minutes), 0);
            
            // Check if slot is in the past for today
            const isPast = isToday && slotTime < now;
            // Check if slot is fully booked
            const isFullyBooked = slot.booked >= maxPerDay;
            const isDisabled = isPast || isFullyBooked;
            
            html += `<div class="time-slot ${isDisabled ? 'disabled' : ''} ${selectedTime === slot.time ? 'selected' : ''}" 
                         data-time="${slot.time}"
                         ${!isDisabled ? 'onclick="selectTime(\'' + slot.time + '\')"' : ''}>
                        ${formatTime(slot.time)} 
                        ${isFullyBooked ? '(Full)' : ''}
                        ${isPast ? '(Past)' : ''}
                    </div>`;
        });
        
        $('#timeSlotsContainer').html(html || '<p class="text-muted">No available slots</p>');
    }

    function formatTime(time) {
        const [hours, minutes] = time.split(':');
        const date = new Date();
        date.setHours(hours, minutes);
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function selectTime(time) {
        selectedTime = time;
        $('.time-slot').removeClass('selected');
        $(`.time-slot[data-time="${time}"]`).addClass('selected');
        
        // Move to consent step
        $('#consentStep').show();
    }

    function goBack() {
        $('#consentStep').hide();
        selectedTime = null;
        $('.time-slot').removeClass('selected');
    }

    // Handle form submission
    $('#bookingForm').submit(function(e) {
        e.preventDefault();
        
        if (!selectedService || !selectedDate || !selectedTime) {
            alert('Please complete all steps');
            return;
        }
        
        const formData = {
            service_id: selectedService,
            appointment_date: selectedDate,
            appointment_time: selectedTime,
            allergies: $('textarea[name="allergies"]').val(),
            medical_conditions: $('textarea[name="medical_conditions"]').val(),
            medications: $('textarea[name="medications"]').val(),
            emergency_contact_name: $('input[name="emergency_contact_name"]').val(),
            emergency_contact_number: $('input[name="emergency_contact_number"]').val(),
            notes: $('textarea[name="notes"]').val(),
            consent: $('#consent').is(':checked')
        };
        
        $.ajax({
            url: '../api/book-appointment.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Appointment booked successfully!');
                    window.location.href = 'my-appointments.php';
                } else {
                    alert(response.message || 'Error booking appointment');
                }
            },
            error: function() {
                alert('Error booking appointment');
            }
        });
    });
    </script>
</body>
</html>