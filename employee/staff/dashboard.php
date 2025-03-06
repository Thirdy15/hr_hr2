<?php
session_start();
if (!isset($_SESSION['e_id']) || !isset($_SESSION['position']) || $_SESSION['position'] !== 'Staff') {
    header("Location: ../../login.php");
    exit();
}

include '../../db/db_conn.php';

$employeeId = $_SESSION['e_id'];
$employeePosition = $_SESSION['position'];
// Fetch the average of the employee's evaluations
$sql = "SELECT 
            AVG(quality) AS avg_quality, 
            AVG(communication_skills) AS avg_communication_skills, 
            AVG(teamwork) AS avg_teamwork, 
            AVG(punctuality) AS avg_punctuality, 
            AVG(initiative) AS avg_initiative,
            COUNT(*) AS total_evaluations 
        FROM admin_evaluations 
        WHERE e_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

// Check if evaluations exist
if ($result->num_rows > 0) {
    $evaluation = $result->fetch_assoc();

    // Calculate the total average
    $totalAverage = (
        $evaluation['avg_quality'] +
        $evaluation['avg_communication_skills'] +
        $evaluation['avg_teamwork'] +
        $evaluation['avg_punctuality'] +
        $evaluation['avg_initiative']
    ) / 5;
} else {
    echo "No evaluations found.";
    exit;
}

// Fetch user info
$sql = "SELECT firstname, middlename, lastname, email, role, position, pfp FROM employee_register WHERE e_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();
$employeeInfo = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Set the profile picture, default if not provided
$profilePicture = !empty($employeeInfo['pfp']) ? $employeeInfo['pfp'] : '../../img/defaultpfp.png';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Employee Dashboard | HR2</title>
    <link href="../../css/styles.css" rel="stylesheet" />
    <link href="../../css/calendar.css" rel="stylesheet"/>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet'/>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>

    <style>
        .collapse {
            transition: width 3s ease;
        }

        #searchInput.collapsing {
            width: 0;
        }

        #searchInput.collapse.show {
            width: 250px; /* Adjust the width as needed */
        }

        .search-bar {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        #search-results {
            position: absolute;
            width: 100%;
            z-index: 1000;
            display: none; /* Hidden by default */
        }

        #search-results a {
            text-decoration: none;
        }

        .form-control:focus + #search-results {
            display: block; /* Show the results when typing */
        }
        

          /* CSS for background blur */
  .blur-background {
    filter: blur(8px); /* You can adjust the blur strength */
    transition: filter 0.3s ease;
  }
    </style>
</head>
<body class="sb-nav-fixed bg-black">
    <?php include 'navbar.php'; ?>
    <div id="layoutSidenav">
       <?php include 'sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main id="main-content">
                <div class="container-fluid position-relative px-4">
                    <div class="">
                        <div class="row align-items-center">
                            <div class="col">
                                <h1 class="mb-4 text-light">Dashboard</h1>
                            </div>
                        </div>
                    </div>
                    <div class="container" id="calendarContainer" 
                         style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1050; 
                        width: 80%; height: 80%; display: none;">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="calendar" class="p-2"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6 mt-2 mb-2">
                            <div class="card bg-dark text-light" style="height: 500px;">
                                <div class="card-header text-light border-bottom border-1 border-secondary">
                                    <h3>Attendance</h3> <!-- Month and Year display -->
                                </div>
                                <div class="card-body overflow-auto" style="max-height: 400px;">
                                    <div class="d-flex justify-content-between align-items-start mb-0">
                                        <div>
                                            <h5 class="fw-bold">Today's Date:</h5>
                                            <a href="../../employee/staff/dashboard.php" id="todaysDate" class="cursor-pointer text-decoration-none">
                                                <span id="todaysDateContent"></span>
                                            </a>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold">Time in:</h5>
                                            <p class="text-warning">08:11 AM</p>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="dateFilter" class="form-label">Filter by Date:</label>
                                        <input type="date" class="form-control" id="dateFilter">
                                    </div>
                                    <hr>
                                    <div class="mb-0">
                                        <h3 class="mb-0" id="monthYearDisplay"></h3>
                                        <div class="row text-center fw-bold">
                                            <div class="col">Sun</div>
                                            <div class="col">Mon</div>
                                            <div class="col">Tue</div>
                                            <div class="col">Wed</div>
                                            <div class="col">Thu</div>
                                            <div class="col">Fri</div>
                                            <div class="col">Sat</div>
                                        </div>

                                        <!-- Calendar rows with attendance status -->
                                        <div id="ATTENDANCEcalendar" class="pt-3 text-light bg-black"></div>
                                    </div>
                                </div>
                                <div class="card-footer text-center d-flex justify-content-around">
                                    <!-- Footer with Next and Previous buttons -->
                                    <button class="btn btn-primary" id="prevMonthBtn">&lt; Prev</button>
                                    <button class="btn btn-primary" id="nextMonthBtn">Next &gt;</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mt-2">
                            <div class="card bg-dark">
                                <div class="card-header text-light border-bottom border-1 border-secondary">
                                    <h3>Performance Ratings | Graph</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mt-2">
                                        <div class="row">
                                            <div class="col-xl-6">
                                                <h5 class="text-light">Quality of Work</h5>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-warning">
                                                        <?php 
                                                            // Display rating label based on avg_quality value
                                                            if ($evaluation['avg_quality'] == 6) {
                                                                echo "Excellent";
                                                                $progressBarClass = "bg-success"; // Green for excellent
                                                            } elseif ($evaluation['avg_quality'] <= 5.99 && $evaluation['avg_quality'] >= 5 ) {
                                                                echo "Good";
                                                                $progressBarClass = "bg-primary"; // Blue for good
                                                            } elseif ($evaluation['avg_quality'] <= 4.99 && $evaluation['avg_quality'] >= 3) {
                                                                echo "Average";
                                                                $progressBarClass = "bg-warning"; // Yellow for average
                                                            } elseif ($evaluation['avg_quality'] <= 2.99 && $evaluation['avg_quality'] >= 0.01) {
                                                                echo "Need Improvements";
                                                                $progressBarClass = "bg-danger"; // Red for needs improvement
                                                            } else {
                                                                echo "Not Yet Evaluated";
                                                                $progressBarClass = "bg-light";
                                                            }                                                                                                    
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="progress">
                                                    <div 
                                                        class="progress-bar <?php echo $progressBarClass; ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo min(100, ($evaluation['avg_quality'] / 6) * 100); ?>%;" 
                                                        aria-valuenow="<?php echo htmlspecialchars($evaluation['avg_quality']); ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="6">
                                                        <?php echo htmlspecialchars(number_format($evaluation['avg_quality'], 2)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-xl-6">
                                                <h5 class="text-light">Communication Skills</h5>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-warning">
                                                            <?php 
                                                                // Display rating label based on avg_quality value
                                                                if ($evaluation['avg_communication_skills'] == 6) {
                                                                    echo "Excellent";
                                                                    $progressBarClass = "bg-success"; // Green for excellent
                                                                } elseif ($evaluation['avg_communication_skills'] <= 5.99 && $evaluation['avg_communication_skills'] >= 5) {
                                                                    echo "Good";
                                                                    $progressBarClass = "bg-primary"; // Blue for good
                                                                } elseif ($evaluation['avg_communication_skills'] <= 4.99 && $evaluation['avg_communication_skills'] >= 3) {
                                                                    echo "Average";
                                                                    $progressBarClass = "bg-warning"; // Yellow for average
                                                                } elseif ($evaluation['avg_communication_skills'] <= 2.99 && $evaluation['avg_communication_skills'] >= 0.01) {
                                                                    echo "Need Improvements";
                                                                    $progressBarClass = "bg-danger";
                                                                } else {
                                                                    echo "Not Yet Evaluated";
                                                                    $progressBarClass = "bg-light"; // Red for needs improvement
                                                                }
                                                            ?>
                                                        </span>
                                                    </div>
                                                <div class="progress">
                                                    <div 
                                                        class="progress-bar <?php echo $progressBarClass; ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo min(100, ($evaluation['avg_communication_skills'] / 6) * 100); ?>%;" 
                                                        aria-valuenow="<?php echo htmlspecialchars($evaluation['avg_communication_skills']); ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="6">
                                                        <?php echo htmlspecialchars(number_format($evaluation['avg_communication_skills'], 2)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>   
                                    <div class="mt-4">
                                        <div class="row">
                                            <div class="col-xl-6">
                                                <h5 class="text-light">Teamwork</h5>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-warning">
                                                        <?php 
                                                            // Display rating label based on avg_quality value
                                                            if ($evaluation['avg_teamwork'] == 6) {
                                                                echo "Excellent";
                                                                $progressBarClass = "bg-success"; // Green for excellent
                                                            } elseif ($evaluation['avg_teamwork'] <= 5.99 && $evaluation['avg_teamwork'] >= 5) {
                                                                echo "Good";
                                                                $progressBarClass = "bg-primary"; // Blue for good
                                                            } elseif ($evaluation['avg_teamwork'] <= 4.99 && $evaluation['avg_teamwork'] >= 3) {
                                                                echo "Average";
                                                                $progressBarClass = "bg-warning"; // Yellow for average
                                                            } elseif ($evaluation['avg_teamwork'] <= 2.99 && $evaluation['avg_teamwork'] >= 0.01) {
                                                                echo "Need Improvements";
                                                                $progressBarClass = "bg-danger";
                                                            } else {
                                                                echo "Not Yet Evaluated";
                                                                $progressBarClass = "bg-light"; // Light gray for not yet evaluated
                                                            }
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="progress">
                                                    <div 
                                                        class="progress-bar <?php echo $progressBarClass; ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo min(100, ($evaluation['avg_teamwork'] / 6) * 100); ?>%;" 
                                                        aria-valuenow="<?php echo htmlspecialchars($evaluation['avg_teamwork']); ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="6">
                                                        <?php echo htmlspecialchars(number_format($evaluation['avg_teamwork'], 2)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-xl-6">
                                                <h5 class="text-light">Punctuality</h5>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-warning">
                                                        <?php 
                                                            // Display rating label based on avg_quality value
                                                            if ($evaluation['avg_punctuality'] == 6) {
                                                                echo "Excellent";
                                                                $progressBarClass = "bg-success"; // Green for excellent
                                                            } elseif ($evaluation['avg_punctuality'] <= 5.99 && $evaluation['avg_punctuality'] >= 5) {
                                                                echo "Good";
                                                                $progressBarClass = "bg-primary"; // Blue for good
                                                            } elseif ($evaluation['avg_punctuality'] <= 4.99 && $evaluation['avg_punctuality'] >= 3) {
                                                                echo "Average";
                                                                $progressBarClass = "bg-warning"; // Yellow for average
                                                            } elseif ($evaluation['avg_punctuality'] <= 2.99 && $evaluation['avg_punctuality'] >= 0.01) {
                                                                echo "Need Improvements";
                                                                $progressBarClass = "bg-danger";
                                                            } else {
                                                                echo "Not Yet Evaluated";
                                                                $progressBarClass = "bg-light"; // Red for needs improvement
                                                            }
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="progress">  
                                                    <div 
                                                        class="progress-bar <?php echo $progressBarClass; ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo min(100, ($evaluation['avg_punctuality'] / 6) * 100); ?>%;" 
                                                        aria-valuenow="<?php echo htmlspecialchars($evaluation['avg_punctuality']); ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="6">
                                                        <?php echo htmlspecialchars(number_format($evaluation['avg_punctuality'], 2)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Rating 5: Initiative -->
                                    <div class="mt-4">
                                        <div class="row">
                                            <div class="col-xl-6">
                                                <h5 class="text-light">Initiative</h5>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-warning">
                                                        <?php 
                                                            // Display rating label based on avg_quality value
                                                            if ($evaluation['avg_initiative'] == 6) {
                                                                echo "Excellent";
                                                                $progressBarClass = "bg-success"; // Green for excellent
                                                            } elseif ($evaluation['avg_initiative'] <= 5.99 && $evaluation['avg_initiative'] >= 5) {
                                                                echo "Good";
                                                                $progressBarClass = "bg-primary"; // Blue for good
                                                            } elseif ($evaluation['avg_initiative'] <= 4.99 && $evaluation['avg_initiative'] >= 3) {
                                                                echo "Average";
                                                                $progressBarClass = "bg-warning"; // Yellow for average
                                                            } elseif ($evaluation['avg_initiative'] <= 2.99 && $evaluation['avg_initiative'] >= 0.01) {
                                                                echo "Need Improvements";
                                                                $progressBarClass = "bg-danger";
                                                            } else {
                                                                echo "Not Yet Evaluated";
                                                                $progressBarClass = "bg-light"; // Red for needs improvement
                                                            }
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="progress">
                                                    <div 
                                                        class="progress-bar <?php echo $progressBarClass; ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo min(100, ($evaluation['avg_initiative'] / 6) * 100); ?>%;" 
                                                        aria-valuenow="<?php echo htmlspecialchars($evaluation['avg_initiative']); ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="6">
                                                        <?php echo htmlspecialchars(number_format($evaluation['avg_initiative'], 2)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-xl-6">
                                                <h5 class="text-light">Overall Rating</h5>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-warning">
                                                        <?php
                                                        // Display rating label based on totalAverage value
                                                        if ($totalAverage == 6) {
                                                            echo "Excellent";
                                                            $progressBarClass = "bg-success"; // Green for excellent
                                                        } elseif ($totalAverage <= 5.99 && $totalAverage >= 5) {
                                                            echo "Good";
                                                            $progressBarClass = "bg-primary"; // Blue for good
                                                        } elseif ($totalAverage <= 4.99 && $totalAverage >= 3) {
                                                            echo "Average";
                                                            $progressBarClass = "bg-warning"; // Yellow for average
                                                        } elseif ($totalAverage <= 2.99 && $totalAverage >= 0.01) {
                                                            echo "Need Improvements";
                                                            $progressBarClass = "bg-danger"; // Red for needs improvement
                                                        } else {
                                                            echo "Not Yet Evaluated";
                                                            $progressBarClass = "bg-light";
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="progress">
                                                    <div
                                                        class="progress-bar <?php echo $progressBarClass; ?>"
                                                        role="progressbar"
                                                        style="width: <?php echo min(100, ($totalAverage / 6) * 100); ?>%;"
                                                        aria-valuenow="<?php echo htmlspecialchars($totalAverage); ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="6">
                                                        <?php echo htmlspecialchars(number_format($totalAverage, 2)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>               
                                    </div>
                                </div>
                            </div>
                        </div>
                    <div class="row mb-4">
                        <div class="col-md-12 mt-2 mb-2">
                            <div class="card bg-dark text-light border-0">
                                <div class="card-header border-bottom border-1 border-secondary">
                                    <h3 class="mb-0">Top Performers | Graph</h3>
                                </div>
                                <div class="card-body">
                                    <ul class="list-group list-group-flush">
                                        <!-- Performer 1 -->
                                        <li class="list-group-item bg-dark text-light d-flex align-items-center justify-content-between border-0">
                                            <div class="d-flex align-items-center">
                                                <img src="../../uploads/profile_pictures/try.jpg" alt="Performer 1" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                <div>
                                                    <h5 class="mb-0">John Doe</h5>
                                                    <small class="text-warning">Sales Manager</small>
                                                </div>
                                            </div>
                                            <div class="progress" style="width: 30%; height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 90%;" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </li>
                                        <!-- Performer 2 -->
                                        <li class="list-group-item bg-dark text-light d-flex align-items-center justify-content-between border-0">
                                            <div class="d-flex align-items-center">
                                                <img src="../../uploads/profile_pictures/pfp3.jpg" alt="Performer 2" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                <div>
                                                    <h5 class="mb-0">Jane Smith</h5>
                                                    <small class="text-warning">Marketing Specialist</small>
                                                </div>
                                            </div>
                                            <div class="progress" style="width: 30%; height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 85%;" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </li>
                                        <!-- Performer 3 -->
                                        <li class="list-group-item bg-dark text-light d-flex align-items-center justify-content-between border-0">
                                            <div class="d-flex align-items-center">
                                                <img src="../../uploads/profile_pictures/logo.jpg" alt="Performer 3" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                <div>
                                                    <h5 class="mb-0">Michael Johnson</h5>
                                                    <small class="text-warning">HR Manager</small>
                                                </div>
                                            </div>
                                            <div class="progress" style="width: 30%; height: 8px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 80%;" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
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
                <div class="modal fade" id="attendanceModal" tabindex="-1" aria-labelledby="timeInfoModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header">
                                <h5 class="modal-title" id="timeInfoModalLabel">Attendance Info</h5>
                                <button type="button" class="btn-close bg-light" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="d-flex justify-content-around">
                                    <div>
                                        <h6 class="fw-bold">Time In:</h6>
                                        <p class="text-info fw-bold" id="timeIn"></p> <!-- Time will be dynamically filled -->
                                    </div>
                                    <div>
                                        <h6 class="fw-bold">Time Out:</h6>
                                        <p class="text-info fw-bold" id="timeOut"></p> <!-- Time will be dynamically filled -->
                                    </div>
                                </div>
                                <!-- New Section for Work Status -->
                                <div class="d-flex justify-content-around mt-3">
                                    <div>
                                        <h6 class="fw-bold">Work Status:</h6>
                                        <p class="text-warning fw-bold" id="workStatus"></p> <!-- Status will be dynamically filled -->
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

<script>
    // for calendar only
    let calendar; // Declare calendar variable globally

    function toggleCalendar() {
        const calendarContainer = document.getElementById('calendarContainer');
        if (calendarContainer.style.display === 'none' || calendarContainer.style.display === '') {
            calendarContainer.style.display = 'block';

            // Initialize the calendar if it hasn't been initialized yet
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
            height: 440,  // Set the height of the calendar to make it small
            events: {
                url: '../../db/holiday.php',  // Endpoint for fetching events
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
        const currentDate = new Date().toLocaleDateString(); // Get the current date
        currentDateElement.textContent = currentDate; // Set the date text
    });

    document.addEventListener('click', function(event) {
        const calendarContainer = document.getElementById('calendarContainer');
        const calendarButton = document.querySelector('button[onclick="toggleCalendar()"]');

        if (!calendarContainer.contains(event.target) && !calendarButton.contains(event.target)) {
            calendarContainer.style.display = 'none';
        }
    });
    // for calendar only end

    function setCurrentTime() {
        const currentTimeElement = document.getElementById('currentTime');
        const currentDateElement = document.getElementById('currentDate');

        const currentDate = new Date();

        // Convert to 12-hour format with AM/PM
        let hours = currentDate.getHours();
        const minutes = currentDate.getMinutes();
        const seconds = currentDate.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';

        hours = hours % 12;
        hours = hours ? hours : 12; // If hour is 0, set to 12

        const formattedHours = hours < 10 ? '0' + hours : hours;
        const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
        const formattedSeconds = seconds < 10 ? '0' + seconds : seconds;

        currentTimeElement.textContent = `${formattedHours}:${formattedMinutes}:${formattedSeconds} ${ampm}`;

        // Format the date in text form (e.g., "January 12, 2025")
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        currentDateElement.textContent = currentDate.toLocaleDateString('en-US', options);
    }

    setCurrentTime();
    setInterval(setCurrentTime, 1000);


// ATTENDANCE
let currentMonth = new Date().getMonth(); // January is 0, December is 11
let currentYear = new Date().getFullYear();
let employeeId = <?php echo $employeeId; ?>; // Employee ID from PHP session
let filteredDay = null; // Track the filtered day

const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

const operationStartTime = new Date();
operationStartTime.setHours(8, 10, 0, 0);

const operationEndTime = new Date();
operationEndTime.setHours(16, 0, 0, 0);

// Function to format time with AM/PM
function formatTimeWithAmPm(time24) {
    if (!time24 || time24 === 'N/A') {
        return 'No data';  // Handle cases where there's no data
    }
    
    // Split time into hours and minutes
    let [hour, minute] = time24.split(':');
    hour = parseInt(hour); // Convert hour to an integer
    const amPm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12; // Convert 0 to 12 for midnight (12 AM)
    return `${hour}:${minute} ${amPm}`;
}

// Function to calculate attendance status
function calculateAttendanceStatus(timeIn, timeOut) {
    let status = '';

    if (timeIn && timeIn !== 'Absent') {
        const timeInDate = new Date(`1970-01-01T${timeIn}:00`);
        if (timeInDate > operationStartTime) {
            status += 'Late';
        }
    }

    if (timeOut && timeOut !== 'Absent') {
        const timeOutDate = new Date(`1970-01-01T${timeOut}:00`);
        if (timeOutDate > operationEndTime) {
            if (status) {
                status += ' & Overtime';
            } else {
                status = 'Overtime';
            }
        }
    }

    return status || 'On Time'; // Default to "On Time" if no issues
}

// Function to render the calendar for a specific month and year
function renderCalendar(month, year, attendanceRecords = {}) {
    const daysInMonth = new Date(year, month + 1, 0).getDate(); // Get total days in the current month
    const firstDay = new Date(year, month, 1).getDay(); // Get the starting day (0 = Sunday, 1 = Monday, etc.)

    let calendarHTML = '<div class="row text-center pt-3">';

    // Add empty columns before the first day of the month
    for (let i = 0; i < firstDay; i++) {
        calendarHTML += '<div class="col"></div>';
    }

    // Fill in the days of the month
    let dayCounter = 1;
    for (let i = firstDay; i < 7; i++) {
        const status = (i === 0) ? 'Day Off' : attendanceRecords[dayCounter] || ''; // Set "Day Off" for Sundays (day 0)
        
        // Add a border if this is the filtered day
        const isFilteredDay = filteredDay && filteredDay.getDate() === dayCounter && filteredDay.getMonth() === month && filteredDay.getFullYear() === year;
        const borderClass = isFilteredDay ? 'border border-2 border-light' : '';

        calendarHTML += `
            <div class="col">
                <button class="btn text-light p-0 ${borderClass}" data-bs-toggle="modal" data-bs-target="#attendanceModal" onclick="showAttendanceDetails(${dayCounter})">
                    <span class="fw-bold ${status === 'Present' ? 'text-success' : status === 'Absent' ? 'text-danger' : status === 'Late' ? 'text-warning' : status === 'Day Off' ? 'text-muted' : ''}">
                        ${dayCounter}
                    </span>
                </button>
            </div>
        `;
        dayCounter++;
    }
    calendarHTML += '</div>';

    // Continue filling rows for the remaining days
    while (dayCounter <= daysInMonth) {
        calendarHTML += '<div class="row text-center pt-3">';
        let dayOfWeek = 0; // Reset for each row

        for (let i = 0; i < 7 && dayCounter <= daysInMonth; i++) {
            const status = (dayOfWeek === 0) ? 'Day Off' : attendanceRecords[dayCounter] || ''; 
            
            // Add a border if this is the filtered day
            const isFilteredDay = filteredDay && filteredDay.getDate() === dayCounter && filteredDay.getMonth() === month && filteredDay.getFullYear() === year;
            const borderClass = isFilteredDay ? 'border border-2 border-light' : '';

            calendarHTML += `
                <div class="col">
                    <button class="btn text-light p-0 ${borderClass}" data-bs-toggle="modal" data-bs-target="#attendanceModal" onclick="showAttendanceDetails(${dayCounter})">
                        <span class="fw-bold ${status === 'Present' ? 'text-success' : status === 'Absent' ? 'text-danger' : status === 'Late' ? 'text-warning' : status === 'Day Off' ? 'text-muted' : ''}">
                            ${dayCounter}
                        </span>
                    </button>
                </div>
            `;
            dayCounter++;
            dayOfWeek++;
        }

        if (dayOfWeek < 7) {
            for (let j = dayOfWeek; j < 7; j++) {
                calendarHTML += '<div class="col"></div>';
            }
        }

        calendarHTML += '</div>';
    }

    document.getElementById('ATTENDANCEcalendar').innerHTML = calendarHTML;
    document.getElementById('monthYearDisplay').textContent = `${monthNames[month]} ${year}`;
    document.getElementById('todaysDate').textContent = `${monthNames[new Date().getMonth()]} ${new Date().getDate()}, ${new Date().getFullYear()}`;
}

// Fetch attendance data for a specific month and year
function fetchAttendance(month, year) {
    fetch(`/HR2/employee_db/staff/fetch_attendance.php?e_id=${employeeId}&month=${month + 1}&year=${year}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            // Handle attendance records and render calendar
            renderCalendar(month, year, data); // Pass attendance data to render calendar
        })
        .catch(error => console.error('Error fetching attendance data:', error));
}

// Show attendance details when a specific day is clicked
function showAttendanceDetails(day) {
    fetch(`/HR2/employee_db/staff/fetch_attendance.php?e_id=${employeeId}&day=${day}&month=${currentMonth + 1}&year=${currentYear}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            // If there's no time_in or time_out, mark it as "Absent"
            const timeInFormatted = data.time_in ? formatTimeWithAmPm(data.time_in) : 'Absent';
            const timeOutFormatted = data.time_out ? formatTimeWithAmPm(data.time_out) : 'Absent';

            // Calculate the status (Late, Overtime, or On Time)
            const attendanceStatus = calculateAttendanceStatus(data.time_in, data.time_out);

            // Update the modal with the formatted time_in, time_out, and attendance status
            document.getElementById('timeIn').textContent = timeInFormatted;
            document.getElementById('timeOut').textContent = timeOutFormatted;
            document.getElementById('workStatus').textContent = attendanceStatus;
            // Set appropriate colors for the status
            const statusElement = document.getElementById('workStatus');
            if (attendanceStatus === 'Late') {
                statusElement.classList.add('text-warning');
                statusElement.classList.remove('text-success', 'text-danger', 'text-muted');
            } else if (attendanceStatus === 'Overtime') {
                statusElement.classList.add('text-info');
                statusElement.classList.remove('text-success', 'text-danger', 'text-muted');
            } else if (attendanceStatus === 'On Time') {
                statusElement.classList.add('text-success');
                statusElement.classList.remove('text-warning', 'text-danger', 'text-muted');
            } else if (attendanceStatus === 'Absent') {
                statusElement.classList.add('text-success');
                statusElement.classList.remove('text-warning', 'text-danger', 'text-muted');
            } else {
                statusElement.classList.add('text-muted');
                statusElement.classList.remove('text-success', 'text-danger', 'text-warning');
            }
        })
        .catch(error => console.error('Error fetching attendance details:', error));
}

// Event listeners for next and previous month buttons
document.getElementById('nextMonthBtn').addEventListener('click', function() {
    currentMonth++;
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    }
    fetchAttendance(currentMonth, currentYear);
});

document.getElementById('prevMonthBtn').addEventListener('click', function() {
    currentMonth--;
    if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    fetchAttendance(currentMonth, currentYear);
});

// Date filter functionality
document.getElementById('dateFilter').addEventListener('change', function () {
    const selectedDate = new Date(this.value); // Get the selected date
    currentMonth = selectedDate.getMonth(); // Update the current month
    currentYear = selectedDate.getFullYear(); // Update the current year
    filteredDay = selectedDate; // Track the filtered day
    fetchAttendance(currentMonth, currentYear); // Fetch and render the calendar for the selected month and year
});

// Fetch the initial calendar for the current month and year
fetchAttendance(currentMonth, currentYear);




</script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'> </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/employee.js"></script>



</body>

</html>