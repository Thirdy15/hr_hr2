<?php
session_start();

if (!isset($_SESSION['e_id']) || !isset($_SESSION['position']) || $_SESSION['position'] !== 'Supervisor') {
    header("Location: ../../login.php");
    exit();
}

include '../../db/db_conn.php';

// Fetch supervisor's ID from the session
$supervisorId = $_SESSION['e_id'];

// Fetch user info
$employeeId = $_SESSION['e_id'];
$sql = "SELECT e_id, firstname, middlename, lastname, birthdate, email, role, position, department, phone_number, address, pfp FROM employee_register WHERE e_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();
$employeeInfo = $result->fetch_assoc();

// Fetch all pending leave requests for supervisors to handle
$sql = "SELECT lr.leave_id, e.e_id, e.firstname, e.lastname, e.department, lr.start_date, lr.end_date, lr.leave_type, lr.proof, lr.status, lr.created_at
        FROM leave_requests lr
        JOIN employee_register e ON lr.e_id = e.e_id
        WHERE lr.status = 'Pending' ORDER BY created_at ASC";  // Fetch only Pending requests

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Fetch holidays from the database
$holidays = [];
$holiday_sql = "SELECT date FROM non_working_days";
$holiday_stmt = $conn->prepare($holiday_sql);
$holiday_stmt->execute();
$holiday_result = $holiday_stmt->get_result();
while ($holiday_row = $holiday_result->fetch_assoc()) {
    $holidays[] = $holiday_row['date']; // Store holidays in an array
}

// Handle approve/deny actions by supervisor
if (isset($_GET['leave_id']) && isset($_GET['status'])) {
    $leave_id = $_GET['leave_id'];
    $status = $_GET['status'];

    // Fetch the specific leave request
    $sql = "SELECT e.department, e.e_id, e.firstname, e.lastname, lr.start_date, lr.end_date, lr.leave_type, lr.proof, lr.status
            FROM leave_requests lr
            JOIN employee_register e ON lr.e_id = e.e_id
            WHERE lr.leave_id = ?";
    $action_stmt = $conn->prepare($sql);
    $action_stmt->bind_param("i", $leave_id);
    $action_stmt->execute();
    $action_result = $action_stmt->get_result();

    if ($action_result->num_rows > 0) {
        $row = $action_result->fetch_assoc();
        $start_date = $row['start_date'];
        $end_date = $row['end_date'];

        // Calculate total leave days excluding Sundays and holidays
        $leave_days = 0;
        $current_date = strtotime($start_date);

        while ($current_date <= strtotime($end_date)) {
            $current_date_str = date('Y-m-d', $current_date);
            // Check if the current day is not a Sunday (0 = Sunday) and not a holiday
            if (date('N', $current_date) != 7 && !in_array($current_date_str, $holidays)) {
                $leave_days++; // Count this day as a leave day
            }
            $current_date = strtotime("+1 day", $current_date); // Move to the next day
        }

        // Check if leave request is still pending before approving or denying
        if ($row['status'] != 'Pending') {
            header("Location: ../../employee/supervisor/leave_request.php?status=already_processed");
            exit();
        }

        if ($status === 'approve') {
            // Update the leave request status to 'Supervisor Approved' and set the supervisor_id
            $update_sql = "UPDATE leave_requests SET status = 'Supervisor Approved', supervisor_approval = 'Supervisor Approved', supervisor_id = ? WHERE leave_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $supervisorId, $leave_id);

            if ($update_stmt->execute()) {
                // After supervisor approval, redirect to supervisor's leave balance page
                header("Location: ../../employee/supervisor/leave_request.php?status=success");
            } else {
                header("Location: ../../employee/supervisor/leave_request.php?status=error");
            }
        } elseif ($status === 'deny') {
            // Check if a comment is provided
            if (isset($_GET['supervisor_comments'])) {
                $supervisor_comments = $_GET['supervisor_comments'];

                // Deny the leave request and add the supervisor's comment
                $delete_sql = "UPDATE leave_requests 
                                SET status = 'Denied', 
                                    supervisor_approval = 'Supervisor Denied', 
                                    admin_approval = 'Supervisor Denied', 
                                    supervisor_id = ?, 
                                    supervisor_comments = ? 
                                WHERE leave_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("isi", $supervisorId, $supervisor_comments, $leave_id);

                if ($delete_stmt->execute()) {
                    // After denial, redirect to supervisor's leave balance page
                    header("Location: ../../employee/supervisor/leave_request.php?status=success");
                } else {
                    header("Location: ../../employee/supervisor/leave_request.php?status=error");
                }
            } else {
                // If no comment is provided, show an error
                header("Location: ../../employee/supervisor/leave_request.php?status=no_comment");
            }
        }
    } else {
        // Leave request not found
        header("Location: ../../employee/supervisor/leave_request.php?status=not_exist");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/png" href="../../img/logo.png">
    <title>Leave Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet">
    <link href="../../css/styles.css" rel="stylesheet" />
    <link href="../../css/calendar.css" rel="stylesheet"/>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' /> <!-- calendar -->
<style>
    .btn {
        transition: transform 0.3s, background-color 0.3s; /* Smooth transition */
        border-radius: 25px; 
    }

    .btn:hover {
        transform: translateY(-2px); /* Raise the button up */
    }
</style>
</head>

<body class="sb-nav-fixed bg-black">
    <?php include 'navbar.php'; ?>
    <div id="layoutSidenav">    
    <?php include 'sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid position-relative px-4">
                    <h1 class="mb-4 text-light">Leave Request</h1>
                    <div class="container" id="calendarContainer" 
                        style="position: fixed; top: 9%; right: 0; z-index: 1050; 
                        width: 700px; display: none;">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="calendar" class="p-2"></div>
                            </div>
                        </div>
                    </div>                     
                    <div class="container py-4">
                        <?php if (isset($_GET['status'])): ?>
                            <div id="status-alert" class="alert 
                                <?php if ($_GET['status'] === 'success'): ?>
                                    alert-success
                                <?php elseif ($_GET['status'] === 'error'): ?>
                                    alert-danger
                                <?php elseif ($_GET['status'] === 'not_exist'): ?>
                                    alert-warning
                                <?php elseif ($_GET['status'] === 'insufficient_balance'): ?>
                                    alert-warning
                                <?php endif; ?>" role="alert">
                                <?php if ($_GET['status'] === 'success'): ?>
                                    Leave request status updated successfully.
                                <?php elseif ($_GET['status'] === 'error'): ?>
                                    Error updating leave request status. Please try again.
                                <?php elseif ($_GET['status'] === 'not_exist'): ?>
                                    The leave request ID does not exist or could not be found.
                                <?php elseif ($_GET['status'] === 'insufficient_balance'): ?>
                                    Insufficient leave balance. The request cannot be approved.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card mb-4 bg-dark text-light">
                        <div class="card-header border-bottom border-1 border-secondary">
                            <i class="fas fa-table me-1"></i>
                            Pending Request
                        </div>
                        <div class="card-body">
                            <table id="datatablesSimple" class="table text-light text-center">
                                <thead>
                                    <tr>
                                        <th>Requested On</th>
                                        <th>Employee ID</th>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Duration of Leave</th>
                                        <th>Deduction Leave</th>
                                        <th>Reason</th>
                                        <th>Proof</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <?php
                                                // Calculate total leave days excluding Sundays and holidays
                                                $leave_days = 0;
                                                $current_date = strtotime($row['start_date']);
                                                $end_date = strtotime($row['end_date']);
                                                
                                                while ($current_date <= $end_date) {
                                                $current_date_str = date('Y-m-d', $current_date);
                                                 // Check if the current day is not a Sunday (0 = Sunday) and not a holiday
                                                if (date('N', $current_date) != 7 && !in_array($current_date_str, $holidays)) {
                                                    $leave_days++; // Count this day as a leave day
                                                }
                                                $current_date = strtotime("+1 day", $current_date); // Move to the next day
                                                }
                                            ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                    if (isset($row['created_at'])) {
                                                        echo htmlspecialchars(date("F j, Y", strtotime($row['created_at']))) . ' <span class="text-warning"> | </span> ' . htmlspecialchars(date("g:i A", strtotime($row['created_at'])));
                                                    } else {
                                                        echo "Not Available";
                                                    }
                                                ?>
                                            </td>
                                                <td><?php echo htmlspecialchars($row['e_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                                <td><?php echo htmlspecialchars(date("F j, Y", strtotime($row['start_date']))) . ' <span class="text-warning"> | </span> ' . htmlspecialchars(date("F j, Y", strtotime($row['end_date']))); ?></td>
                                                <td><?php echo htmlspecialchars($leave_days); ?> day/s</td>
                                                <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                                <td>
                                                    <?php if (!empty($row['proof'])): ?>
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proofModal<?php echo $row['proof']; ?>">View</button>
                                                    <?php else: ?>
                                                        No proof provided
                                                    <?php endif; ?>
                                                </td>
                                                <div class="modal fade" id="proofModal<?php echo $row['proof']; ?>" tabindex="-1" aria-labelledby="proofModalLabel<?php echo $row['proof']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content bg-dark text-light" style="width: 600px; height: 500px;">
                                                            <div class="modal-header border-bottom border-warning">
                                                                <h5 class="modal-title" id="proofModalLabel<?php echo $row['proof']; ?>">Proof of Leave</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body d-flex align-items-center justify-content-center" style="overflow-y: auto; height: calc(100% - 80px);">
                                                                <div id="proofCarousel<?php echo $row['proof']; ?>" class="carousel slide d-flex align-items-center justify-content-center" data-bs-ride="false">
                                                                    <div class="carousel-inner">
                                                                        <?php
                                                                            // Assuming proof field contains a comma-separated list of file names
                                                                            $filePaths = explode(',', $row['proof']);  
                                                                            $isActive = true;  // To set the first item as active
                                                                            $fileCount = count($filePaths);  // Count the number of files
                                                                            $baseURL = 'http://localhost/HR2/proof/';  // Define the base URL for file access

                                                                            foreach ($filePaths as $filePath) {
                                                                                $filePath = trim($filePath);  // Clean the file path
                                                                                $fullFilePath = $baseURL . $filePath;  // Construct the full URL for the file
                                                                                $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

                                                                                // Check if the file is an image (e.g., jpg, jpeg, png, gif)
                                                                                $imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
                                                                                if (in_array(strtolower($fileExtension), $imageTypes)) {
                                                                                    echo '<div class="carousel-item ' . ($isActive ? 'active' : '') . '">';
                                                                                    echo '<img src="' . htmlspecialchars($fullFilePath) . '" alt="Proof of Leave" class="d-block w-100" style="max-height: 400px; object-fit: contain;">';
                                                                                    echo '</div>';
                                                                                    $isActive = false;
                                                                                }
                                                                                // Check if the file is a PDF (this will just show an embed for PDFs)
                                                                                elseif (strtolower($fileExtension) === 'pdf') {
                                                                                    echo '<div class="carousel-item ' . ($isActive ? 'active' : '') . '">';
                                                                                    echo '<embed src="' . htmlspecialchars($fullFilePath) . '" type="application/pdf" width="100%" height="400px" />';
                                                                                    echo '</div>';
                                                                                    $isActive = false;
                                                                                }
                                                                                // Handle other document types (e.g., docx, txt) – just provide a link to view the document
                                                                                else {
                                                                                    echo '<div class="carousel-item ' . ($isActive ? 'active' : '') . '">';
                                                                                    echo '<a href="' . htmlspecialchars($fullFilePath) . '" target="_blank" class="btn btn-primary">View Document</a>';
                                                                                    echo '</div>';
                                                                                    $isActive = false;
                                                                                }
                                                                            }
                                                                        ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php if ($fileCount > 1): ?>
                                                                <button class="carousel-control-prev btn btn-secondary position-absolute top-50 start-0 translate-middle-y w-auto" type="button" data-bs-target="#proofCarousel<?php echo $row['proof']; ?>" data-bs-slide="prev">
                                                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                                    <span class="visually-hidden">Previous</span>
                                                                </button>
                                                                <button class="carousel-control-next btn btn-secondary position-absolute top-50 end-0 translate-middle-y w-auto" type="button" data-bs-target="#proofCarousel<?php echo $row['proof']; ?>" data-bs-slide="next">
                                                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                                    <span class="visually-hidden">Next</span>
                                                                </button>
                                                            <?php endif; ?>
                                                            <div class="modal-footer border-top border-warning">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center mb-0">
                                                        <button class="btn btn-success btn-sm me-2" onclick="confirmAction('approve', <?php echo $row['leave_id']; ?>)">Approve</button>
                                                        <button class="btn btn-danger btn-sm" onclick="confirmAction('deny', <?php echo $row['leave_id']; ?>)">Deny</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
                <!-- Deny Reason Modal -->
                <div class="modal fade" id="denyReasonModal" tabindex="-1" aria-labelledby="denyReasonModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header">
                                <h5 class="modal-title" id="denyReasonModalLabel">Reason for Denial</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <textarea id="denyReason" class="form-control" placeholder="Enter reason for denial..." rows="3"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="submitDeny()">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Custom Confirmation Modal -->
                <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header">
                                <h5 class="modal-title" id="confirmationModalLabel">Confirm Denial</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to deny this leave request?
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="proceedWithDenial()">Yes, Deny</button>
                            </div>
                        </div>
                    </div>
                </div>
             <?php include 'footer.php'; ?>
        </div>
    </div>
<script>
    //CALENDAR 
    let calendar;
        function toggleCalendar() {
            const calendarContainer = document.getElementById('calendarContainer');
                if (calendarContainer.style.display === 'none' || calendarContainer.style.display === '') {
                    calendarContainer.style.display = 'block';
                    if (!calendar) {
                        initializeCalendar();
                        }
                    } else {
                        calendarContainer.style.display = 'none';
                    }
        }

        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    height: 440,  
                    events: {
                    url: '../../db/holiday.php',  
                    method: 'GET',
                    failure: function() {
                    alert('There was an error fetching events!');
                    }
                    }
                });

                calendar.render();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const currentDateElement = document.getElementById('currentDate');
            const currentDate = new Date().toLocaleDateString(); 
            currentDateElement.textContent = currentDate; 
        });

        document.addEventListener('click', function(event) {
            const calendarContainer = document.getElementById('calendarContainer');
            const calendarButton = document.querySelector('button[onclick="toggleCalendar()"]');

                if (!calendarContainer.contains(event.target) && !calendarButton.contains(event.target)) {
                    calendarContainer.style.display = 'none';
                    }
        });
        //CALENDAR END

        //TIME 
        function setCurrentTime() {
            const currentTimeElement = document.getElementById('currentTime');
            const currentDateElement = document.getElementById('currentDate');

            const currentDate = new Date();
    
            currentDate.setHours(currentDate.getHours() + 0);
                const hours = currentDate.getHours();
                const minutes = currentDate.getMinutes();
                const seconds = currentDate.getSeconds();
                const formattedHours = hours < 10 ? '0' + hours : hours;
                const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
                const formattedSeconds = seconds < 10 ? '0' + seconds : seconds;

            currentTimeElement.textContent = `${formattedHours}:${formattedMinutes}:${formattedSeconds}`;
            currentDateElement.textContent = currentDate.toLocaleDateString();
        }
        setCurrentTime();
        setInterval(setCurrentTime, 1000);
        //TIME END

        // Automatically hide the alert after 10 seconds (10,000 milliseconds)
    setTimeout(function() {
        var alertElement = document.getElementById('status-alert');
        if (alertElement) {
            alertElement.style.transition = "opacity 1s ease"; // Add transition for smooth fade-out
            alertElement.style.opacity = 0; // Set the opacity to 0 (fade out)
            
            setTimeout(function() {
                alertElement.remove(); // Remove the element from the DOM after fade-out
            }, 1000); // Wait 1 second after fade-out to remove the element completely
        }
    }, 5000); // 10 seconds delay


    let currentLeaveId = null; // Variable to store the leave ID
    let denyReason = ''; // Variable to store the denial reason

    function confirmAction(action, leaveId) {
        if (action === 'deny') {
            // For denial, show the modal to collect the reason
            currentLeaveId = leaveId; // Store the leave ID
            const denyReasonModal = new bootstrap.Modal(document.getElementById('denyReasonModal'));
            denyReasonModal.show(); // Show the modal
        } else {
            // For approval or other actions, use a confirmation dialog
            let confirmation = confirm(`Are you sure you want to ${action} this leave request?`);
            if (confirmation) {
                window.location.href = `leave_request.php?leave_id=${leaveId}&status=${action}`;
            }
        }
    }

    function submitDeny() {
        denyReason = document.getElementById('denyReason').value;

        if (!denyReason) {
            alert('Please enter a reason for denial.');
            return;
        }

        // Hide the reason modal
        const denyReasonModal = bootstrap.Modal.getInstance(document.getElementById('denyReasonModal'));
        denyReasonModal.hide();

        // Show the custom confirmation modal
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmationModal.show();
    }

    function proceedWithDenial() {
        // Send the data to the server
        fetch(`leave_request.php?leave_id=${currentLeaveId}&status=deny&supervisor_comments=${encodeURIComponent(denyReason)}`, {
            method: 'GET',
        })
        .then(response => {
            if (response.ok) {
                alert('Leave request denied successfully.');
                location.reload(); // Reload the page to reflect changes
            } else {
                alert('Failed to deny leave request.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });

        // Close the confirmation modal
        const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
        confirmationModal.hide();
    }

</script>
<!-- Only keep the latest Bootstrap 5 version -->
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'> </script>
<script src="../../js/datatables-simple-demo.js"></script>
<script src="../../js/employee.js"></script>
</body>
</html>