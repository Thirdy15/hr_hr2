<?php
session_start();
if (!isset($_SESSION['e_id']))  {
    header("Location: ../../employee/login.php"); // Redirect to login if not logged in
    exit();
}

include '../../db/db_conn.php';

// Fetch user info
$employeeId = $_SESSION['e_id'];
$sql = "SELECT firstname, middlename, lastname, email, role, position, department, pfp FROM employee_register WHERE e_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();
$employeeInfo = $result->fetch_assoc();
$stmt->close();

// Fetch employees in the same department
$department = $employeeInfo['department'];
$sql = "SELECT e_id, firstname, middlename, lastname, email, role, phone_number, address, pfp FROM employee_register WHERE department = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $department);
$stmt->execute();
$employeesResult = $stmt->get_result();
$employees = $employeesResult->fetch_all(MYSQLI_ASSOC); // Fetch all employees
$stmt->close();
$conn->close();

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
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .equal-height {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
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
        .fixed-table {
            position: relative;
            margin: 0 auto; /* Center the table */
            width: 80%; /* Make the table smaller */
        }
        .sb-sidenav-toggled .fixed-table {
            margin: 0 auto; /* Center the table */
        }
        .modal-content .table {
            font-size: 1.25rem; /* Increase table font size */
        }
        .modal-content h3, .modal-content h5 {
            font-size: 2rem; /* Increase header font size */
        }
        .modal-content .table th, .modal-content .table td {
            padding: 1rem; /* Increase padding for table cells */
        }
        .star-rating .star {
            cursor: pointer; /* Change cursor to pointer when hovering over stars */
        }
    </style>
</head>
<body class="sb-nav-fixed bg-black sb-sidenav-toggled">
<nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark border-bottom border-1 border-secondary">
        <a class="navbar-brand ps-3 text-muted" href="../../employee/supervisor/dashboard.php">Employee Portal</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><i class="fas fa-bars text-light"></i></button>
        <div class="d-flex ms-auto me-0 me-md-3 my-2 my-md-0 align-items-center">
            <div class="text-light me-3 p-2 rounded shadow-sm bg-gradient" id="currentTimeContainer"
            style="background: linear-gradient(45deg, #333333, #444444); border-radius: 5px;">
                <span class="d-flex align-items-center ms-2">
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
            <div class="dropdown search-container" style="position: relative;">
                <form class="d-none d-md-inline-block form-inline">
                    <div class="input-group">
                        <!-- Search Input -->
                        <input class="form-control collapse" id="searchInput" type="text" placeholder="Search for..." aria-label="Search for..." aria-describedby="btnNavbarSearch" data-bs-toggle="dropdown" aria-expanded="false" />
                        <button class="btn btn-outline-warning rounded" id="btnNavbarSearch" type="button" data-bs-toggle="collapse" data-bs-target="#searchInput" aria-expanded="false" aria-controls="searchInput">
                            <i id="searchIcon" class="fas fa-search"></i> <!-- Initial Icon -->
                        </button>
                    </div>
                    <ul id="searchResults" class="dropdown-menu list-group mt-2 bg-transparent" style="width: 100%;"></ul>
                </form>
            </div>
            </form>
        </div>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                    <div class="nav">
                        <div class="sb-sidenav-menu-heading text-center text-muted">Profile</div>
                        <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle text-light d-flex justify-content-center ms-4" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img src="<?php echo (!empty($employeeInfo['pfp']) && $employeeInfo['pfp'] !== 'defaultpfp.png')
                                        ? htmlspecialchars($employeeInfo['pfp'])
                                        : '../../img/defaultpfp.jpg'; ?>"
                                        class="rounded-circle border border-light" width="120" height="120" alt="" />
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="../../employee//profile.php">Profile</a></li>
                                    <li><a class="dropdown-item" href="#!">Settings</a></li>
                                    <li><a class="dropdown-item" href="#!">Activity Log</a></li>
                                    <li><hr class="dropdown-divider" /></li>
                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</a></li>
                                </ul>
                            </li>
                            <li class="nav-item text-light d-flex ms-3 flex-column align-items-center text-center">
                                <span class="big text-light mb-1">
                                    <?php echo htmlspecialchars($employeeInfo['firstname'] . ' ' . $employeeInfo['middlename'] . ' ' . $employeeInfo['lastname']); ?>
                                </span>
                                <span class="big text-light">
                                    <?php echo htmlspecialchars($employeeInfo['position']); ?>
                                </span>
                            </li>
                        </ul>
                        <div class="sb-sidenav-menu-heading text-center text-muted border-top border-1 border-secondary mt-3">Employee Dashboard</div>
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
                                <a class="nav-link text-light" href="../../employee/staff/attendance.php">Attendance Scanner</a>
                                <a class="nav-link text-light" href="">View Attendance Record</a>
                            </nav>
                        </div>
                        <a class="nav-link collapsed text-light" href="#" data-bs-toggle="collapse" data-bs-target="#collapseLM" aria-expanded="false" aria-controls="collapseLM">
                            <div class="sb-nav-link-icon "><i class="fas fa-calendar-times"></i></div>
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
                                <a class="nav-link text-light" href="../../employee/staff/evaluation.php">View Ratings</a>
                            </nav>
                            <nav class="sb-sidenav-menu-nested nav">
                                <a class="nav-link text-light" href="../../employee/staff/department.php">Department Evaluation</a>
                            </nav>
                        </div>
                        <a class="nav-link collapsed text-light" href="#" data-bs-toggle="collapse" data-bs-target="#collapseSR" aria-expanded="false" aria-controls="collapseSR">
                            <div class="sb-nav-link-icon"><i class="fa fa-address-card"></i></div>
                            Social Recognition
                            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                        </a>
                        <div class="collapse" id="collapseSR" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                            <nav class="sb-sidenav-menu-nested nav">
                                <a class="nav-link text-light" href="">View Your Rating</a>
                            </nav>
                        </div>
                        <div class="sb-sidenav-menu-heading text-center text-muted border-top border-1 border-secondary mt-3">Feedback</div>
                        <a class="nav-link collapsed text-light" href="#" data-bs-toggle="collapse" data-bs-target="#collapseFB" aria-expanded="false" aria-controls="collapseFB">
                            <div class="sb-nav-link-icon"><i class="fas fa-exclamation-circle"></i></div>
                            Report Issue
                            <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                        </a>
                        <div class="collapse" id="collapseFB" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                            <nav class="sb-sidenav-menu-nested nav">
                                <a class="nav-link text-light" href="">Report Issue</a>
                            </nav>
                        </div>
                    </div>
                </div>
                <div class="sb-sidenav-footer bg-black border-top border-1 border-secondary">
                    <div class="small text-light">Logged in as: <?php echo htmlspecialchars($employeeInfo['role']); ?></div>
                </div>
            </nav>
        </div>
        <div id="layoutSidenav_content">
            <main class="bg-black">
                <div class="container-fluid position-relative px-4 fixed-table">
                    <h1 class="mb-4 text-light text-center">Department Evaluation</h1> <!-- Center the title -->

                    <div class="container" id="calendarContainer"
                        style="position: fixed; top: 9%; right: 0; z-index: 1050;
                        width: 700px; display: none;">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="calendar" class="p-2"></div>
                            </div>
                        </div>
                    </div>
                    <div class="py-4"></div>
                    <div class="card mb-4 bg-dark text-light">
                        <div class="card-header border-bottom border-1 border-secondary">
                            <i class="fas fa-table me-1"></i>
                            Department Employees
                        </div>
                        <div class="card-body">
                            <table id="datatablesSimple" class="table text-light text-center">
                                <thead class="thead-light">
                                    <tr class="text-center text-light">
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Phone Number</th>
                                        <th>Address</th>
                                        <th>Evaluate Employee</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($employees)): ?>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr class="text-center text-light">
                                                <td><?php echo htmlspecialchars($employee['e_id']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['firstname'] . ' ' . $employee['middlename'] . ' ' . $employee['lastname']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['role']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['phone_number']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['address']); ?></td>
                                                <td class='d-flex justify-content-around'>
                                                    <button class="btn btn-success btn-sm me-2"
                                                        onclick="openModal(<?php echo $employee['e_id']; ?>, '<?php echo htmlspecialchars($employee['firstname']); ?>', '<?php echo htmlspecialchars($employee['lastname']); ?>', '<?php echo htmlspecialchars($employee['email']); ?>', '<?php echo htmlspecialchars($employee['role']); ?>', '<?php echo htmlspecialchars($employee['phone_number']); ?>', '<?php echo htmlspecialchars($employee['address']); ?>', '<?php echo htmlspecialchars($employee['pfp']); ?>')">
                                                        Evaluate
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center">No records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
            <footer class="py-4 bg-light mt-auto bg-dark border-top border-1 border-secondary">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; Your Website 2023</div>
                        <div>
                            <a href="#">Privacy Policy</a>
                            &middot;
                            <a href="#">Terms &amp; Conditions</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Modal Structure -->
    <div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeModalLabel">Employee Evaluation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="employeeEvaluationForm">
                        <!-- Employee Info -->
                        <div class="mb-3 text-center">
                            <img id="employeeProfilePicture" src="../../img/defaultpfp.png" class="rounded-circle border border-light mb-3" width="120" height="120" alt="Profile Picture" />
                            <h3 id="employeeName"></h3>
                            <h5 id="employeeRole"></h5>
                        </div>
                        <!-- Evaluation Questions -->
                        <div id="evaluationQuestions">
                            <table class="table table-bordered text-light">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Question</th>
                                        <th>Rating</th>
                                    </tr>
                                </thead>
                                <tbody id="evaluationQuestionsBody"></tbody>
                            </table>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitEvaluation()">Submit</button>
                </div>
            </div>
        </div>
    </div>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../js/employee.js"></script>

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
        hours = hours ? 12 : 12; // If hour is 0, set to 12

        const formattedHours = hours < 10 ? '0' + hours : hours;
        const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
        const formattedSeconds = seconds < 10 ? '0' + seconds : seconds;

        currentTimeElement.textContent = `${formattedHours}:${formattedMinutes}:${formattedSeconds} ${ampm}`;

        // Format the date in text form (e.g., "January 12, 2025")
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        currentDateElement.textContent = currentDate.toLocaleDateString('en-US', options);
    }

    // Update the time every second
    setInterval(setCurrentTime, 1000);

    const features = [
        { name: "Dashboard", link: "../../employee/staff/dashboard.php", path: "Employee Dashboard" },
        { name: "Attendance Scanner", link: "../../employee/staff/attendance.php", path: "Time and Attendance/Attendance Scanner" },
        { name: "Leave Request", link: "../../employee/staff/leave_request.php", path: "Leave Management/Leave Request" },
        { name: "Evaluation Ratings", link: "../../employee/staff/evaluation.php", path: "Performance Management/Evaluation Ratings" },
        { name: "File Leave", link: "../../employee/staff/leave_file.php", path: "Leave Management/File Leave" },
        { name: "View Your Rating", link: "../../employee/staff/social_recognition.php", path: "Social Recognition/View Your Rating" },
        { name: "Report Issue", link: "../../employee/staff/report_issue.php", path: "Feedback/Report Issue" }
    ];

    // Handle search input change
    document.getElementById('searchInput').addEventListener('input', function () {
        let input = this.value.toLowerCase();
        let results = '';

        if (input) {
            // Filter the features based on the search input
            const filteredFeatures = features.filter(feature =>
                feature.name.toLowerCase().includes(input)
            );

            if (filteredFeatures.length > 0) {
                // Generate the HTML for the filtered results
                filteredFeatures.forEach(feature => {
                    results += `
                        <a href="${feature.link}" class="list-group-item list-group-item-action">
                            ${feature.name}
                            <br>
                            <small class="text-muted">${feature.path}</small>
                        </a>`;
                });
            } else {
                // If no matches found, show "No result found"
                results = '<li class="list-group-item list-group-item-action">No result found</li>';
            }
        }

        // Update the search results with the filtered features
        document.getElementById('searchResults').innerHTML = results;

        if (!input) {
            document.getElementById('searchResults').innerHTML = ''; // Clears the dropdown if input is empty
        }
    });

    // Handle collapse event to clear search input when hidden
    const searchInputElement = document.getElementById('searchInput');
    searchInputElement.addEventListener('hidden.bs.collapse', function () {
        // Clear the search input and search results when the input collapses
        searchInputElement.value = '';  // Clear the input
        document.getElementById('searchResults').innerHTML = '';  // Clear the search results
    });

    function openModal(e_id, firstName, lastName, email, role, phoneNumber, address, pfp) {
        // Set employee info
        document.getElementById('employeeName').textContent = `Name: ${firstName} ${lastName}`;
        document.getElementById('employeeRole').textContent = `Role: ${role}`;
        document.getElementById('employeeProfilePicture').src = pfp ? `../../img/${pfp}` : '../../img/defaultpfp.png';

        // Generate evaluation questions
        const evaluationQuestions = {
            "Quality of Work": [
                "How do you rate the employee's attention to detail?",
                "How would you evaluate the accuracy of the employee's work?",
                "Does the employee consistently meet job requirements?"
            ],
            "Communication Skills": [
                "Does the employee communicate clearly?",
                "Is the employee responsive to feedback?",
                "How effectively does the employee listen to others?"
            ],
            "Teamwork": [
                "Does the employee collaborate well with others?",
                "How well does the employee contribute to team success?",
                "Does the employee support team members when needed?"
            ],
            "Punctuality": [
                "Is the employee consistent in meeting deadlines?",
                "How often does the employee arrive on time?",
                "Does the employee respect others' time?"
            ],
            "Initiative": [
                "Does the employee take initiative without being asked?",
                "How frequently does the employee suggest improvements?",
                "Does the employee show a proactive attitude?"
            ]
        };

        const questionsBody = document.getElementById('evaluationQuestionsBody');
        questionsBody.innerHTML = ''; // Clear previous questions

        // Generate HTML for questions in table format
        for (const [category, questions] of Object.entries(evaluationQuestions)) {
            questions.forEach((question, index) => {
                questionsBody.innerHTML += `
                    <tr>
                        <td style="font-size: 1.25rem;">${category}</td>
                        <td style="font-size: 1.25rem;">${question}</td>
                        <td>
                            <div class="star-rating">
                                ${[1, 2, 3, 4, 5, 6].map(value => `
                                    <span class="star" data-value="${value}" data-category="${category.replace(/\s/g, '')}q${index}" style="font-size: 1.5rem;">&#9733;</span>
                                `).join('')}
                            </div>
                        </td>
                    </tr>
                `;
            });
        }

        // Open the modal
        var myModal = new bootstrap.Modal(document.getElementById('employeeModal'));
        myModal.show();

        // Add event listeners for star rating
        document.querySelectorAll('.star').forEach(star => {
            star.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                const category = this.getAttribute('data-category');
                document.querySelectorAll(`.star[data-category="${category}"]`).forEach(s => {
                    s.style.color = s.getAttribute('data-value') <= value ? 'gold' : 'white';
                });
            });
        });
    }

    function submitEvaluation() {
        const evaluations = [];
        const questionsBody = document.getElementById('evaluationQuestionsBody');

        questionsBody.querySelectorAll('.star-rating').forEach(starRating => {
            const selectedStar = Array.from(starRating.children).find(star => star.style.color === 'gold');
            if (selectedStar) {
                evaluations.push({
                    question: selectedStar.getAttribute('data-category'),
                    rating: selectedStar.getAttribute('data-value')
                });
            }
        });

        const totalQuestions = questionsBody.querySelectorAll('.star-rating').length;

        if (evaluations.length !== totalQuestions) {
            alert('Please complete the evaluation before submitting.');
            return;
        }

        // Save evaluations to the database
        fetch('../../employee/save_evaluation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ evaluations })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Evaluation submitted successfully!');
                var myModal = bootstrap.Modal.getInstance(document.getElementById('employeeModal'));
                myModal.hide();
            } else {
                alert('Failed to submit evaluation.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting the evaluation.');
        });
    }

    // Sidebar toggle functionality
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        document.body.classList.toggle('sb-sidenav-toggled');
    });
</script>
</body>
</html>
