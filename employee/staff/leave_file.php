<?php
session_start();
include '../../db/db_conn.php';

if (!isset($_SESSION['e_id']) || !isset($_SESSION['position']) || $_SESSION['position'] !== 'Staff') {
    header("Location: ../../login.php");
    exit();
}

// Fetch user info from the employee_register table
$employeeId = $_SESSION['e_id'];
$sql = "SELECT e_id, firstname, middlename, lastname, birthdate, gender, email, role, position, department, phone_number, address, pfp FROM employee_register WHERE e_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();
$employeeInfo = $result->fetch_assoc();

if (!$employeeInfo) {
    die("Error: Employee information not found.");
}

$gender = $employeeInfo['gender']; // Fetch gender

// Fetch the available leaves from the employee_leaves table (including both male and female leave types)
$leavesQuery = "SELECT 
                    bereavement_leave, emergency_leave, maternity_leave, mcw_special_leave, 
                    parental_leave, service_incentive_leave, sick_leave, vacation_leave, vawc_leave,
                    bereavement_leave_male,emergency_leave_male, parental_leave_male, 
                    paternity_leave_male, service_incentive_leave_male, sick_leave_male, vacation_leave_male 
                FROM employee_leaves 
                WHERE employee_id = ?";
$leavesStmt = $conn->prepare($leavesQuery);
$leavesStmt->bind_param("i", $employeeId);
$leavesStmt->execute();
$leavesResult = $leavesStmt->get_result();
$leavesInfo = $leavesResult->fetch_assoc();

// If no leave information is found, set default values for leave types
if (!$leavesInfo) {
    $leaveTypes = [
        'bereavement_leave', 'emergency_leave', 'maternity_leave', 'mcw_special_leave', 
        'parental_leave', 'service_incentive_leave', 'sick_leave', 'vacation_leave', 'vawc_leave',
        'bereavement_leave_male', 'emergency_leave_male', 'parental_leave_male', 'paternity_leave_male', 
        'service_incentive_leave_male', 'sick_leave_male', 'vacation_leave_male'
    ];
    $leavesInfo = array_fill_keys($leaveTypes, 0);
}

// Fetch the used leave by summing up approved leave days
$usedLeaveQuery = "SELECT start_date, end_date, SUM(DATEDIFF(end_date, start_date) + 1) AS used_leaves 
                   FROM leave_requests 
                   WHERE e_id = ? AND status = 'approved'
                   GROUP BY e_id";
$usedLeaveStmt = $conn->prepare($usedLeaveQuery);
$usedLeaveStmt->bind_param("i", $employeeId);
$usedLeaveStmt->execute();
$usedLeaveResult = $usedLeaveStmt->get_result();
$usedLeaveRow = $usedLeaveResult->fetch_assoc();
$usedLeave = $usedLeaveRow['used_leaves'] ?? 0; // Default to 0 if no leave has been used

// Calculate total available leaves based on gender
$totalAvailableLeaves = 0;
if ($employeeInfo['gender'] === 'Male') {
    // For male employees
    $totalAvailableLeaves = 
        $leavesInfo['bereavement_leave_male'] +
        $leavesInfo['emergency_leave_male'] +
        $leavesInfo['parental_leave_male'] +
        $leavesInfo['paternity_leave_male'] +
        $leavesInfo['service_incentive_leave_male'] +
        $leavesInfo['sick_leave_male'] +
        $leavesInfo['vacation_leave_male'];
} else {
    // For female employees
    $totalAvailableLeaves = 
        $leavesInfo['bereavement_leave'] +
        $leavesInfo['emergency_leave'] +
        $leavesInfo['maternity_leave'] +
        $leavesInfo['mcw_special_leave'] +
        $leavesInfo['parental_leave'] +
        $leavesInfo['service_incentive_leave'] +
        $leavesInfo['sick_leave'] +
        $leavesInfo['vacation_leave'] +
        $leavesInfo['vawc_leave'];
}

// Calculate remaining total leaves by subtracting used leaves
$remainingLeaves = $totalAvailableLeaves;

// Close the database connection
$stmt->close();
$leavesStmt->close();
$usedLeaveStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request Form</title>
    <link href="../../css/styles.css" rel="stylesheet" />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="../../css/calendar.css" rel="stylesheet"/>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="sb-nav-fixed bg-black">
    <nav class="sb-topnav navbar navbar-expand navbar-dark border-bottom border-1 border-warning bg-dark">
        <a class="navbar-brand ps-3 text-muted" href="../../employee/staff/dashboard.php">Employee Portal</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars text-light"></i></button>
        <div class="d-flex ms-auto me-0 me-md-3 my-2 my-md-0 align-items-center">
            <div class="text-light me-3 p-2 rounded shadow-sm bg-gradient" id="currentTimeContainer" 
                style="background: linear-gradient(45deg, #333333, #444444); border-radius: 5px;">
                <span class="d-flex align-items-center">
                    <span class="pe-2">
                        <i class="fas fa-clock"></i> 
                        <span id="currentTime">00:00:00</span>
                    </span>
                    <button class="btn btn-outline-warning btn-sm ms-2" type="button" onclick="toggleCalendar()">
                        <i class="fas fa-calendar-alt"></i>
                        <span id="currentDate">00/00/0000</span>
                    </button>
                </span>
            </div>
            <form class="d-none d-md-inline-block form-inline">
                <div class="input-group">
                    <input class="form-control" type="text" placeholder="Search for..." aria-label="Search for..." aria-describedby="btnNavbarSearch" />
                    <button class="btn btn-warning" id="btnNavbarSearch" type="button"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion bg-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu ">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading text-center text-muted">Your Profile</div>
                        <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                            <li class="nav-item dropdown text">
                                <a class="nav-link dropdown-toggle text-light d-flex justify-content-center ms-4" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="<?php echo (!empty($employeeInfo['pfp']) && $employeeInfo['pfp'] !== 'defaultpfp.png') 
                                        ? htmlspecialchars($employeeInfo['pfp']) 
                                        : '../../img/defaultpfp.jpg'; ?>" 
                                        class="rounded-circle border border-light" width="120" height="120" alt="" />
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="../../employee/staff/profile.php">Profile</a></li>
                                    <li><a class="dropdown-item" href="#!">Settings</a></li>
                                    <li><a class="dropdown-item" href="#!">Activity Log</a></li>
                                    <li><hr class="dropdown-divider" /></li>
                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                                </ul>
                            </li>
                            <li class="nav-item text-light d-flex ms-3 flex-column align-items-center text-center">
                                <span class="big text-light mb-1">
                                    <?php
                                        if ($employeeInfo) {
                                        echo htmlspecialchars($employeeInfo['firstname'] . ' ' . $employeeInfo['middlename'] . ' ' . $employeeInfo['lastname']);
                                        } else {
                                        echo "User information not available.";
                                        }
                                    ?>
                                </span>      
                                <span class="big text-light">
                                    <?php
                                        if ($employeeInfo) {
                                        echo htmlspecialchars($employeeInfo['role']);
                                        } else {
                                        echo "User information not available.";
                                        }
                                    ?>
                                </span>
                            </li>
                        </ul>
                        <div class="sb-sidenav-menu-heading text-center text-muted border-top border-1 border-warning mt-3">Employee Dashboard</div>
                        <a class="nav-link text-light" href="../../employee/staff/dashboard.php">
                            <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                            Dashboard
                        </a>
                        <a class="nav-link collapsed text-light" href="#" data-bs-toggle="collapse" data-bs-target="#collapseTAD" aria-expanded="false" aria-controls="collapseTAD">
                            <div class="sb-nav-link-icon"><i class="fa fa-address-card"></i></div>
                            Time and Attendance
                            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                        </a>
                        <div class="collapse" id="collapseTAD" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                            <nav class="sb-sidenav-menu-nested nav">
                                <a class="nav-link text-light" href="../../employee/staff/attendance.php">Attendance</a>
                                <a class="nav-link text-light" href="">Timesheet</a>
                            </nav>
                        </div>
                        <a class="nav-link collapsed text-light" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLM" aria-expanded="false" aria-controls="collapseLM">
                            <div class="sb-nav-link-icon"><i class="fas fa-calendar-times"></i></div>
                            Leave Management
                            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                        </a>
                        <div class="collapse" id="collapseLM" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                            <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link text-light" href="../../employee/staff/leave_file.php">File Leave</a>
                            <a class="nav-link text-light" href="../../employee/staff/leave_request.php">Leave Request</a>
                            </nav>
                        </div>
                        <a class="nav-link collapsed text-light" href="#" data-bs-toggle="collapse" data-bs-target="#collapsePM" aria-expanded="false" aria-controls="collapsePM">
                            <div class="sb-nav-link-icon"><i class="fas fa-line-chart"></i></div>
                            Performance Management
                            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                        </a>
                        <div class="collapse" id="collapsePM" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                            <nav class="sb-sidenav-menu-nested nav">
                            <a class="nav-link text-light" href="../../employee/staff/evaluation.php">Evaluation</a>
                            </nav>
                        </div>
                        <a class="nav-link collapsed text-light" href="#" data-bs-toggle="collapse" data-bs-target="#collapseSR" aria-expanded="false" aria-controls="collapseSR">
                            <div class="sb-nav-link-icon"><i class="fa fa-address-card"></i></div>
                            Social Recognition
                            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                        </a>
                        <div class="collapse" id="collapseSR" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                            <nav class="sb-sidenav-menu-nested nav">
                                <a class="nav-link text-light" href="">Awardee</a>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="sb-sidenav-footer bg-black text-light border-top border-1 border-warning">
                    <div class="small">Logged in as: <?php echo htmlspecialchars($employeeInfo['role']); ?></div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main class="container-fluid position-relative bg-black px-4">
                <div class="container" id="calendarContainer" 
                    style="position: fixed; top: 9%; right: 0; z-index: 1050; 
                    width: 700px; display: none;">
                    <div class="row">
                        <div class="col-md-12">
                            <div id="calendar" class="p-2"></div>
                        </div>
                    </div>
                </div> 
                <h1 class="mb-2 text-light">File Leave</h1>                   
                <div class="card bg-black py-4">
                    <?php if (isset($_SESSION['status_message'])): ?>
                        <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content bg-dark text-light">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="statusModalLabel">
                                            <i class="fa fa-info-circle text-light me-2 fs-4"></i> Message
                                        </h5>
                                        <button type="button" class="btn-close text-light" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body align-items-center">
                                        <?php echo $_SESSION['status_message']; ?>
                                        <div class="d-flex justify-content-center mt-3">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ok</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var myModal = new bootstrap.Modal(document.getElementById('statusModal'));
                                myModal.show();
                            });
                        </script>
                        <?php unset($_SESSION['status_message']); // Clear the message after displaying ?>
                    <?php endif; ?>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card leave-balance-card bg-dark text-light">
                                <div class="card-body text-center">
                                    <h3 class="card-title">Leave Information</h3>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="p-3">
                                                <h5>Overall Available Leave</h5>
                                                <p class="fs-4 text-success"><?php echo htmlspecialchars($remainingLeaves); ?> days</p>
                                                <a class="btn btn-success" href="../../employee/staff/leaveDetails.php"> View leave details</a>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3">
                                                <h5>Used Leave</h5>
                                                <p class="fs-4 text-danger"><?php echo htmlspecialchars($usedLeave); ?> days</p>
                                                <a class="btn btn-danger" href="../../employee/staff/leaveHistory.php"> View leave history</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <form id="leave-request-form" action="../../employee_db/staff/leave_conn.php"class="needs-validation" method="POST" enctype="multipart/form-data" novalidate>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card leave-form text bg-dark text-light">
                                    <div class="card-body">
                                        <h3 class="card-title text-center mb-4">Request Leave</h3>
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-3">
                                                <div class="position-relative mb-3 mb-md-0">
                                                    <label for="name" class="fw-bold position-absolute text-light" 
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Name:</label>
                                                    <input type="text" class="form-control fw-bold bg-dark border border-2 border-secondary text-light" 
                                                        style="height: 60px; padding-top: 15px; padding-bottom: 15px;" id="name" name="name" value="<?php echo htmlspecialchars($employeeInfo['firstname'] . ' ' . $employeeInfo['lastname']); ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="position-relative mb-3 mb-md-0">
                                                    <label for="department" class="fw-bold position-absolute text-light" 
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Department:</label>
                                                    <input type="text" class="form-control fw-bold bg-dark border border-2 border-secondary text-light" 
                                                        style="height: 60px; padding-top: 15px; padding-bottom: 15px;" id="department" name="department" value="<?php echo htmlspecialchars($employeeInfo['department']); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-3">
                                                <div class="position-relative mb-3 mb-md-0">
                                                    <label class="fw-bold position-absolute text-light" 
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;" for="leave_type">Leave Type</label>
                                                    <select id="leave_type" name="leave_type" class="form-control form-select fw-bold bg-dark border border-2 border-secondary text-light" 
                                                        style="height: 60px; padding-top: 15px; padding-bottom: 15px;" required>
                                                        <option value="" disabled selected>Select leave type</option>
                                                        <option value="Bereavement Leave">Bereavement Leave</option>
                                                        <option value="Emergency Leave">Emergency Leave</option>
                                                        <option value="Maternity Leave" class="female-leave">Maternity Leave</option>
                                                        <option value="MCW Special Leave" class="female-leave">MCW Special Leave Benefit</option>
                                                        <option value="Parental Leave">Parental Leave</option>
                                                        <option value="Paternity Leave" class="male-leave">Paternity Leave</option>
                                                        <option value="Service Incentive Leave">Service Incentive Leave</option>
                                                        <option value="Sick Leave">Sick Leave</option>
                                                        <option value="Vacation Leave">Vacation Leave</option>
                                                        <option value="VAWC Leave" class="female-leave">VAWC Leave</option>
                                                    </select>
                                                    <div class="invalid-feedback">Please select leave type.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="position-relative mb-3 mb-md-0">
                                                    <label for="leave_days" class="fw-bold position-absolute text-light" 
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Number of Days</label>
                                                    <input type="number" name="leave_days" id="leave_days" class="form-control fw-bold bg-dark border border-2 border-secondary text-light" 
                                                        style="height: 60px; padding-top: 15px; padding-bottom: 15px;" min="1" max="30" placeholder="" required readonly>
                                                    <div class="invalid-feedback">Please set a value.</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-3">
                                                <div class="position-relative mb-3 mb-md-0">
                                                    <label for="start_date" class="fw-bold position-absolute text-light" 
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Start Date</label>
                                                    <input type="date" id="start_date" name="start_date" class="form-control fw-bold bg-dark border border-2 border-secondary text-light" 
                                                        style="height: 60px; padding-top: 15px; padding-bottom: 15px;" required>
                                                    <div class="invalid-feedback">Please set a date.</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="position-relative mb-3 mb-md-0">
                                                    <label for="end_date" class="fw-bold position-absolute text-light" 
                                                        style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">End Date</label>
                                                    <input type="date" id="end_date" name="end_date" class="form-control fw-bold bg-dark border border-2 border-secondary text-light" 
                                                        style="height: 60px; padding-top: 15px; padding-bottom: 15px;" required>
                                                    <div class="invalid-feedback">Please set a date.</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="position-relative mb-3 mb-md-0">
                                                <label for="proof" class="fw-bold position-absolute text-light" 
                                                    style="top: -10px; left: 15px; background-color: #212529; padding: 0 5px;">Attach Proof</label>
                                                <input type="file" id="proof" name="proof[]" class="form-control fw-bold bg-dark border border-2 border-secondary text-light" 
                                                    style="height: 60px; padding-top: 15px; padding-bottom: 15px;" accept="*/*" multiple>
                                                <small class="form-text text-warning">Note: Please upload the necessary proof (image or PDF) to support your leave request. You may upload multiple files,
                                                but a single file is sufficient for your request to be considered valid.</small>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="button" class="btn btn-danger me-2" onclick="resetForm()">Clear</button>
                                            <button type="submit" class="btn btn-primary">Submit Leave Request</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
                <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header">
                                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to log out?
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn border-secondary text-light" data-bs-dismiss="modal">Cancel</button>
                                <form action="../../employee/logout.php" method="POST">
                                    <button type="submit" class="btn btn-danger">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>  
            <footer class="py-4 bg-dark text-light mt-auto border-top border-warning">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; Your Website 2024</div>
                        <div>
                            <a href="#">Privacy Policy</a>
                            &middot;
                            <a href="#">Terms & Conditions</a>
                        </div>
                    </div>
                </div>
            </footer>
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

        //LEAVE DAYS
        document.getElementById('start_date').addEventListener('change', calculateLeaveDays);
        document.getElementById('end_date').addEventListener('change', calculateLeaveDays);

        function calculateLeaveDays() {
            const start_date = document.getElementById('start_date').value;
            const end_date = document.getElementById('end_date').value;
            
            if (start_date && end_date) {
                const start = new Date(start_date);
                const end = new Date(end_date);
                let totalDays = 0;

                // Loop through the dates between start and end dates
                for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
                    // Exclude Sundays (0 is Sunday)
                    if (date.getDay() !== 0) {
                        totalDays++;
                    }
                }

                // Update the number of days in the input field
                document.getElementById('leave_days').value = totalDays;
            }
        }
        //LEAVE DAYS END


        //LEAVE REQUEST
        document.addEventListener('DOMContentLoaded', function () {
            const leaveType = document.getElementById('leave_type');
            const leaveDays = document.getElementById('leave_days');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            let holidays = [];

            // Fetch holidays from the server
            fetch('../../employee_db/getHolidays.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Holidays fetched:', data);
                    if (Array.isArray(data)) {
                        holidays = data;
                    } else {
                        console.error('Expected an array of holidays, but received:', data);
                    }
                })
                .catch(error => {
                    console.error('Error fetching holidays:', error);
                });

            function calculateEndDate(startDate, days) {
                let count = 0;
                let currentDate = new Date(startDate);
                
                while (count < days) {
                    currentDate.setDate(currentDate.getDate() + 1);

                    const currentDateString = currentDate.toISOString().split('T')[0];
                    if (currentDate.getDay() !== 0 && !holidays.includes(currentDateString)) {
                        count++;
                    }
                }
                return currentDate.toISOString().split('T')[0];
            }

            function isInvalidStartDate(date) {
                const dateString = date.toISOString().split('T')[0];
                const todayString = new Date().toISOString().split('T')[0]; // Today's date in YYYY-MM-DD format

                // Check if the date is today or a holiday or a Sunday
                return date.getDay() === 0 || holidays.includes(dateString) || dateString === todayString;
            }

            // Event listener for start date change
            startDate.addEventListener('change', function () {
                const selectedStartDate = new Date(startDate.value);
                
                // Check if the selected start date is invalid
                if (isInvalidStartDate(selectedStartDate)) {
                    alert("You cannot file leave on Sundays, holidays, or the current day.");
                    startDate.value = ''; // Clear the selected start date
                    endDate.value = ''; // Clear the end date as well
                    return;
                }

                if (leaveType.value === 'Paternity Leave' && startDate.value) {
                    const endDateValue = calculateEndDate(startDate.value, 7);
                    endDate.value = endDateValue;
                } else if (leaveType.value === 'Maternity Leave' && startDate.value) {
                    const endDateValue = calculateEndDate(startDate.value, 105);
                    endDate.value = endDateValue;
                } else {
                    endDate.value = '';
                }
            });
        });

        //LEAVE REQUEST END

        //GENDER BASED
        // Get the gender from PHP
        const gender = "<?php echo addslashes($gender); ?>";

        const femaleLeaveOptions = document.querySelectorAll('.female-leave');
        const maleLeaveOptions = document.querySelectorAll('.male-leave');

        // Hide all gender-specific options by default
        femaleLeaveOptions.forEach(option => option.style.display = 'none');
        maleLeaveOptions.forEach(option => option.style.display = 'none');

        // Show gender-specific leave options based on the user's gender
        if (gender === 'Female') {
            femaleLeaveOptions.forEach(option => option.style.display = 'block');  // Show Female Leave Options
        } else if (gender === 'Male') {
            maleLeaveOptions.forEach(option => option.style.display = 'block');  // Show Male Leave Options
        }

        function resetForm() {
            document.getElementById('leave-request-form').reset();  // Reset the form
        }
        //GENDER BASED

        //VALIDATION
        (function () {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
        })();
        //VALIDATION

</script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'> </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="../../js/employee.js"></script>
</body>
</html>
