<?php
// Start session and check admin login
session_start();
if (!isset($_SESSION['a_id'])) {
    header("Location: ../../admin/login.php");
    exit();
}

// Include database connection
include '../db/db_conn.php';

// Fetch user info
$adminId = $_SESSION['a_id'];
$sql = "SELECT a_id, firstname, middlename, lastname, birthdate, email, role, department, phone_number, address, pfp FROM admin_register WHERE a_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$adminInfo = $result->fetch_assoc();

function calculateProgressCircle($averageScore) {
    return ($averageScore / 10) * 100;
}

function getTopEmployeesByCriterion($conn, $criterion, $criterionLabel, $index) {
    // Whitelist allowed criteria to avoid SQL injection
    $allowedCriteria = ['quality', 'communication_skills', 'teamwork', 'punctuality', 'initiative'];
    if (!in_array($criterion, $allowedCriteria)) {
        echo "<p>Invalid criterion selected</p>";
        return;
    }

    // SQL query to fetch the highest average score for each employee
    $sql = "SELECT e.e_id, e.firstname, e.lastname, e.department, e.pfp, e.email,
                   AVG(ae.$criterion) AS avg_score,
                   AVG(ae.quality) AS avg_quality,
                   AVG(ae.communication_skills) AS avg_communication,
                   AVG(ae.teamwork) AS avg_teamwork,
                   AVG(ae.punctuality) AS avg_punctuality,
                   AVG(ae.initiative) AS avg_initiative
            FROM employee_register e
            JOIN admin_evaluations ae ON e.e_id = ae.e_id
            GROUP BY e.e_id
            ORDER BY avg_score DESC
            LIMIT 5"; // Fetch top 5 employees

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    // Path to the default profile picture
    $defaultPfpPath = '../img/defaultpfp.jpg';
    $defaultPfp = base64_encode(file_get_contents($defaultPfpPath));

    // Output the awardee's information for each criterion
    ?>
    <div class='category' id='category-<?php echo $index; ?>'>
        <h3 class='text-center mt-4'><?php echo $criterionLabel; ?></h3>
    <?php
    if ($result->num_rows > 0) {
        $employeeIndex = 0;
        while ($row = $result->fetch_assoc()) {
            $employeeIndex++;
            ?>
            <div class='employee-card' id='employee-<?php echo $index; ?>-<?php echo $employeeIndex; ?>' style='display: none;'>
                <?php
                // Check if profile picture exists, else use the default picture
                if (!empty($row['pfp'])) {
                    $pfp = base64_encode($row['pfp']);  // Assuming pfp is a BLOB
                } else {
                    $pfp = $defaultPfp;
                }

                // Calculate percentage for the progress circle
                $scorePercentage = calculateProgressCircle($row['avg_score']);
                ?>
                <div class='metrics-container'>
                    <!-- Left metrics -->
                    <div class='metrics-column'>
                        <div class='metric-box fade-in'>
                            <span class='metric-label'>Quality of Work</span>
                            <span class='metric-value'><?php echo round($row['avg_quality'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact quality score
                                $qualityScore = $row['avg_quality'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($qualityScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($qualityScore)) {
                                        $decimalPart = $qualityScore - floor($qualityScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class='metric-box fade-in' style='animation-delay: 0.2s;'>
                            <span class='metric-label'>Communication Skills</span>
                            <span class='metric-value'><?php echo round($row['avg_communication'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact communication skills score
                                $communicationScore = $row['avg_communication'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($communicationScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($communicationScore)) {
                                        $decimalPart = $communicationScore - floor($communicationScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <!-- Center profile section -->
                    <div class='profile-section'>
                        <div class='progress-circle-container'>
                            <div class='progress-circle' data-progress='<?php echo $scorePercentage; ?>'>
                                <div class='profile-image-container'>
                                    <?php if (!empty($pfp)) { ?>
                                        <img src='data:image/jpeg;base64,<?php echo $pfp; ?>' alt='Profile Picture' class='profile-image'>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class='profile-info'>
                            <h2 class='employee-name'><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></h2>
                            <p class='department-name'><?php echo htmlspecialchars($row['department']); ?></p>
                        </div>
                        <div class='employee-id fade-in' style='animation-delay: 0.8s;'>
                            Employee ID: <?php echo htmlspecialchars($row['e_id']); ?>
                        </div>
                        <!-- New metric box below employee ID -->
                        <div class='metric-box fade-in' style='animation-delay: 0.8s;'>
                            <span class='metric-label'>Initiative</span>
                            <span class='metric-value'><?php echo round($row['avg_initiative'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact initiative score
                                $initiativeScore = $row['avg_initiative'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($initiativeScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($initiativeScore)) {
                                        $decimalPart = $initiativeScore - floor($initiativeScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <!-- Add buttons for comments and reactions -->
                        <div class='comment-reaction-buttons'>
                            <button class='btn btn-primary' onclick='openCommentModal(<?php echo $row['e_id']; ?>)'>Write a Comment</button>
                            <div class='comment-input-container'>
                            <button class="btn btn-primary react-btn" id="react-button-<?php echo $row['e_id']; ?>" onclick='showReactions(this, <?php echo $row['e_id']; ?>)'>React</button>
                            <div id='reaction-menu-<?php echo $row['e_id']; ?>' class='reaction-dropdown'>
                                    <button onclick='selectReaction("👍 Like", <?php echo $row['e_id']; ?>)'>👍</button>
                                    <button onclick='selectReaction("❤️ Heart", <?php echo $row['e_id']; ?>)'>❤️</button>
                                    <button class='close-btn' onclick='closeReactions(<?php echo $row['e_id']; ?>)'>Close</button>
                                </div>
                            </div>
                        </div>
                        <div class='navigation-buttons'>
                            <button class='btn btn-primary' onclick='showPreviousEmployee(<?php echo $index; ?>)'>Previous</button>
                            <button class='btn btn-primary' onclick='showNextEmployee(<?php echo $index; ?>)'>Next</button>
                        </div>
                    </div>
                    <!-- Right metrics -->
                    <div class='metrics-column'>
                        <div class='metric-box fade-in' style='animation-delay: 0.4s;'>
                            <span class='metric-label'>Teamwork</span>
                            <span class='metric-value'><?php echo round($row['avg_teamwork'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact teamwork score
                                $teamworkScore = $row['avg_teamwork'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($teamworkScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($teamworkScore)) {
                                        $decimalPart = $teamworkScore - floor($teamworkScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class='metric-box fade-in' style='animation-delay: 0.6s;'>
                            <span class='metric-label'>Punctuality</span>
                            <span class='metric-value'><?php echo round($row['avg_punctuality'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact punctuality score
                                $punctualityScore = $row['avg_punctuality'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($punctualityScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($punctualityScore)) {
                                        $decimalPart = $punctualityScore - floor($punctualityScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        ?>
        <p class='text-center'>No outstanding employees found for <?php echo $criterionLabel; ?>.</p>
        <?php
    }
    ?>
    </div>
    <?php
    $stmt->close();
}

function getAllEmployees($conn) {
    $sql = "SELECT e.e_id, e.firstname, e.lastname, e.department, e.pfp, e.email,
                   AVG(ae.quality) AS avg_quality,
                   AVG(ae.communication_skills) AS avg_communication,
                   AVG(ae.teamwork) AS avg_teamwork,
                   AVG(ae.punctuality) AS avg_punctuality,
                   AVG(ae.initiative) AS avg_initiative
            FROM employee_register e
            JOIN admin_evaluations ae ON e.e_id = ae.e_id
            GROUP BY e.e_id
            ORDER BY e.e_id ASC"; // Fetch all employees

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    // Path to the default profile picture
    $defaultPfpPath = '../img/defaultpfp.jpg';
    $defaultPfp = base64_encode(file_get_contents($defaultPfpPath));

    // Output the employee's information
    ?>
    <div class='category' id='category-all' style='display: none;'>
        <h3 class='text-center mt-4'>All Employees</h3>
    <?php
    if ($result->num_rows > 0) {
        $employeeIndex = 0;
        while ($row = $result->fetch_assoc()) {
            $employeeIndex++;
            ?>
            <div class='employee-card' id='employee-all-<?php echo $employeeIndex; ?>' style='display: none;'>
                <?php
                // Check if profile picture exists, else use the default picture
                if (!empty($row['pfp'])) {
                    $pfp = base64_encode($row['pfp']);  // Assuming pfp is a BLOB
                } else {
                    $pfp = $defaultPfp;
                }

                // Calculate percentage for the progress circle
                $scorePercentage = calculateProgressCircle(($row['avg_quality'] + $row['avg_communication'] + $row['avg_teamwork'] + $row['avg_punctuality'] + $row['avg_initiative']) / 5);
                ?>
                <div class='metrics-container'>
                    <!-- Left metrics -->
                    <div class='metrics-column'>
                        <div class='metric-box fade-in'>
                            <span class='metric-label'>Quality of Work</span>
                            <span class='metric-value'><?php echo round($row['avg_quality'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact quality score
                                $qualityScore = $row['avg_quality'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($qualityScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($qualityScore)) {
                                        $decimalPart = $qualityScore - floor($qualityScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class='metric-box fade-in' style='animation-delay: 0.2s;'>
                            <span class='metric-label'>Communication Skills</span>
                            <span class='metric-value'><?php echo round($row['avg_communication'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact communication skills score
                                $communicationScore = $row['avg_communication'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($communicationScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($communicationScore)) {
                                        $decimalPart = $communicationScore - floor($communicationScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <!-- Center profile section -->
                    <div class='profile-section'>
                        <div class='progress-circle-container'>
                            <div class='progress-circle' data-progress='<?php echo $scorePercentage; ?>'>
                                <div class='profile-image-container'>
                                    <?php if (!empty($pfp)) { ?>
                                        <img src='data:image/jpeg;base64,<?php echo $pfp; ?>' alt='Profile Picture' class='profile-image'>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class='profile-info'>
                            <h2 class='employee-name'><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></h2>
                            <p class='department-name'><?php echo htmlspecialchars($row['department']); ?></p>
                        </div>
                        <div class='employee-id fade-in' style='animation-delay: 0.8s;'>
                            Employee ID: <?php echo htmlspecialchars($row['e_id']); ?>
                        </div>
                        <!-- New metric box below employee ID -->
                        <div class='metric-box fade-in' style='animation-delay: 0.8s;'>
                            <span class='metric-label'>Initiative</span>
                            <span class='metric-value'><?php echo round($row['avg_initiative'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact initiative score
                                $initiativeScore = $row['avg_initiative'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($initiativeScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($initiativeScore)) {
                                        $decimalPart = $initiativeScore - floor($initiativeScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <!-- Add buttons for comments and reactions -->
                        <div class='comment-reaction-buttons'>
                            <button class='btn btn-primary btn-sm' onclick='openCommentModal(<?php echo $row['e_id']; ?>)'>Write a Comment</button>
                            <div class='comment-input-container'>
                                <button class='btn btn-primary btn-sm react-btn' onclick='showReactions(this, <?php echo $row['e_id']; ?>)'>React</button>
                                <div id='reaction-menu-<?php echo $row['e_id']; ?>' class='reaction-dropdown'>
                                    <button onclick='selectReaction("👍 Like", <?php echo $row['e_id']; ?>)'>👍</button>
                                    <button onclick='selectReaction("😂 Haha", <?php echo $row['e_id']; ?>)'>😂</button>
                                    <button onclick='selectReaction("❤️ Heart", <?php echo $row['e_id']; ?>)'>❤️</button>
                                    <button onclick='selectReaction("😡 Angry", <?php echo $row['e_id']; ?>)'>😡</button>
                                    <button onclick='selectReaction("😢 Sad", <?php echo $row['e_id']; ?>)'>😢</button>
                                    <button class='close-btn' onclick='closeReactions(<?php echo $row['e_id']; ?>)'>Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right metrics -->
                    <div class='metrics-column'>
                        <div class='metric-box fade-in' style='animation-delay: 0.4s;'>
                            <span class='metric-label'>Teamwork</span>
                            <span class='metric-value'><?php echo round($row['avg_teamwork'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact teamwork score
                                $teamworkScore = $row['avg_teamwork'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($teamworkScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($teamworkScore)) {
                                        $decimalPart = $teamworkScore - floor($teamworkScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class='metric-box fade-in' style='animation-delay: 0.6s;'>
                            <span class='metric-label'>Punctuality</span>
                            <span class='metric-value'><?php echo round($row['avg_punctuality'], 2); ?></span>
                            <div class='star-rating' style='margin-top: 5px;'>
                                <?php
                                // Get the exact punctuality score
                                $punctualityScore = $row['avg_punctuality'];
                                for ($i = 1; $i <= 6; $i++) {
                                    if ($i <= floor($punctualityScore)) {
                                        echo "<i class='bi bi-star-fill' style='color: gold;'></i>";
                                    } elseif ($i == ceil($punctualityScore)) {
                                        $decimalPart = $punctualityScore - floor($punctualityScore);
                                        if ($decimalPart >= 0.2 && $decimalPart <= 0.9) {
                                            echo "<i class='bi bi-star-half' style='color: gold;'></i>";
                                        } else {
                                            echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                        }
                                    } else {
                                        echo "<i class='bi bi-star' style='color: lightgray;'></i>";
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        ?>
        <p class='text-center'>No employees found.</p>
        <?php
    }
    ?>
    
    </div>
    <?php
    $stmt->close();
}

function getComments($conn, $employeeId) {
    $sql = "SELECT comment FROM comments WHERE e_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row['comment'];
    }
    $stmt->close();
    return $comments;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outstanding Employees</title>
    <link href="../css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet" />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="../css/calendar.css" rel="stylesheet"/>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .card {
            border: 2px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(233, 233, 233, 0.1);
            padding: 10px;
            background-color: #f9f9f9;
        }
        .card-img {
            border-radius: 10px;
        }
        .card-body {
            padding-left: 10px;
        }
        .card-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .card-text {
            font-size: 14px;
        }
        .category {
            display: none;
        }
        .btn {
            transition: transform 0.3s, background-color 0.3s;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 14px;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .emoji-container {
            display: none;
            gap: 15px;
            cursor: pointer;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .emoji {
            font-size: 30px;
            transition: transform 0.2s ease;
            padding: 10px;
        }
        .emoji:hover {
            transform: scale(1.2);
        }
        .reaction {
            margin-top: 15px;
            font-size: 18px;
            color: #333;
        }
        .saved-reaction {
            margin-top: 10px;
            color: #007bff;
        }
        .open-btn {
            font-size: 16px;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .open-btn:hover {
            background-color: #0056b3;
        }
        .reaction-count {
            font-size: 18px;
            color: #333;
            margin-top: 10px;
            display: flex;
            gap: 15px;
        }
        .reaction-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .selected-emoji {
            font-size: 50px;
            margin-top: 20px;
        }
        .employee-card {
            background: rgb(33, 37, 41);
            border-radius: 20px;
            padding: 2rem;
            max-width: 1200px;
            margin: 2rem auto;
            color: rgba(248, 249, 250);
        }

        .dashboard-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 2rem;
            color: white;
        }

        .metrics-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .metrics-column {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .metric-box {
            background:rgb(16, 17, 18);
            border-radius: 15px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            margin-top: 2rem;
            margin-bottom: 2rem;
            width: 100%;
        }

        .metric-label {
            color: rgba(248, 249, 250);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.875rem;
            font-weight: bold;
        }

        .profile-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .progress-circle-container {
            position: relative;
            width: 200px;
            height: 200px;
        }

        .progress-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            position: relative;
            background: #1e3a8a;
        }

        .profile-image-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #22d3ee;
            overflow: hidden;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            text-align: center;
            margin-top: 1rem;
        }

        .employee-name {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .department-name {
            color: #22d3ee;
            font-size: 0.875rem;
        }

        .employee-id {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 1rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0;
        }

        @keyframes progressCircle {
            from {
                stroke-dashoffset: 628;
            }
            to {
                stroke-dashoffset: var(--progress);
            }
        }

        @media (max-width: 768px) {
            .metrics-container {
                grid-template-columns: 1fr;
            }

            .employee-card {
                margin: 1rem;
                padding: 1rem;
            }
        }

        .progress-ring {
            position: absolute;
            top: 0;
            left: 0;
            transform: rotate(-90deg);
        }

        .progress-ring__circle {
            transition: stroke-dashoffset 0.5s ease-out;
        }

        .comment-reaction-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .modal-right {
            position: fixed;
            top: 10%;
            right: 0;
            width: 300px;
            height: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: none;
            z-index: 1000;
            overflow: visible;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .modal-body {
            margin-top: 20px;
            overflow-y: visible;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
        }

        .comment-button {
            background-color: #1877F2;
            color: white;
            padding: 5px 10px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }

        .comment-display {
            background: #f1f1f1;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }

        .reaction-container {
            position: relative;
            display: inline-block;
        }

        .like-button {
            background-color: #1877F2;
            color: white;
            padding: 5px 10px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .reaction-menu {
            display: none;
            position: absolute;
            top: -40px;
            left: 0;
            background: white;
            border-radius: 10px;
            padding: 5px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 10;
            flex-direction: row;
            gap: 5px;
        }

        .reaction {
            cursor: pointer;
            font-size: 20px;
            transition: transform 0.2s;
        }

        .reaction:hover {
            transform: scale(1.3);
        }

        .comment-section {
            margin-top: 10px;
        }

        .comment-input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            color: black;
            margin-bottom: 10px;
        }

        .comment-list {
            max-height: 300px; /* Adjust the height as needed */
            overflow-y: auto;
            padding-right: 10px; /* Add padding to account for the scrollbar */
        }

        .comment {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .react-btn:hover {
    transform: translateY(-2px);
}

.reaction-dropdown {
    display: none;
    position: absolute;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 5px;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 1;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.reaction-dropdown.show {
    display: block;
    opacity: 1;
}

.comment-reaction-buttons:hover .reaction-dropdown {
    display: block;
    opacity: 1;
}

.reaction-dropdown:hover {
    opacity: 1;
}

    
     
    .navigation-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    gap: 10px;
    }

    .navigation-buttons .btn {
        width: 55%;
        
    }


        .reaction-dropdown button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            margin: 5px;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 5px;
        }

        .comment-input-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .comment-input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            color: black;
        }
        .comment-list {
            margin-top: 5px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .comment {
            background: transparent;
            padding: 5px;
            border-radius: 5px;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal-content {
            background-color: rgba(51, 51, 51, 0.9);
            color: white;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-label {
            display: block;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .star-rating {
            display: inline-flex;
            align-items: center;
        }

        .star {
            font-size: 24px;
            color: gold;
            margin-right: 5px;
        }

        .star:not(.filled) {
            color: #ccc;
        }

        .modal-right {
            position: fixed;
            bottom: 0;
            left: 55%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 600px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: none;
            z-index: 1000;
            animation: slideUpFromBottom 0.5s forwards;
            height: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .modal-body {
            margin-top: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
        }

        .comment-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .comment {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 10px;
            background: #f1f1f1;
            color: black;
            word-wrap: break-word;
        }

        @keyframes slideUpFromBottom {
            0% {
                transform: translateY(100%) translateX(-50%);
                opacity: 0;
            }
            100% {
                transform: translateY(0) translateX(-50%);
                opacity: 1;
            }
        }

    </style>
</head>
<body class="sb-nav-fixed bg-black">
    <?php include 'navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'sidebar.php'; ?>
    <div id="layoutSidenav_content">
        <main class="container-fluid position-relative bg-black">
            <h1 class="mb-2 text-light ms-2">Outstanding Employees by Evaluation Criteria</h1>
            <div class="container text-light">
                <!-- Top Employees for Different Criteria -->
                <?php getTopEmployeesByCriterion($conn, 'quality', 'Quality of Work', 1); ?>
                <?php getTopEmployeesByCriterion($conn, 'communication_skills', 'Communication Skills', 2); ?>
                <?php getTopEmployeesByCriterion($conn, 'teamwork', 'Teamwork', 3); ?>
                <?php getTopEmployeesByCriterion($conn, 'punctuality', 'Punctuality', 4); ?>
                <?php getTopEmployeesByCriterion($conn, 'initiative', 'Initiative', 5); ?>
                <?php getAllEmployees($conn); ?>

                <!-- Navigation buttons for manually controlling the categories -->
                
            </div>
        </main>
            <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content bg-dark text-light">
                        <div class="modal-header border-bottom border-secondary">
                            <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to log out?
                        </div>
                        <div class="modal-footer border-top border-secondary">
                            <button type="button" class="btn border-secondary text-light" data-bs-dismiss="modal">Cancel</button>
                            <form action="../employee/logout.php" method="POST">
                                <button type="submit" class="btn btn-danger">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php include 'footer.php'; ?>
    </div>
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-transparent border-0">
                    <div class="modal-body d-flex flex-column align-items-center justify-content-center">
                            <!-- Bouncing coin spinner -->
                            <div class="coin-spinner"></div>
                            <div class="mt-3 text-light fw-bold">Please wait...</div>
                        </div>
                    </div>
                </div>
           </div>
<script>
        document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('.loading');
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));

        // Loop through each button and add a click event listener
        buttons.forEach(button => {
            button.addEventListener('click', function (event) {
                // Show the loading modal
                loadingModal.show();

                // Disable the button to prevent multiple clicks
                this.classList.add('disabled');

                // Handle form submission buttons
                if (this.closest('form')) {
                    event.preventDefault(); // Prevent the default form submit

                    // Submit the form after a short delay
                    setTimeout(() => {
                        this.closest('form').submit();
                    }, 1500);
                }
                // Handle links
                else if (this.tagName.toLowerCase() === 'a') {
                    event.preventDefault(); // Prevent the default link behavior

                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 1500);
                }
            });
        });

        // Hide the loading modal when navigating back and enable buttons again
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) { // Check if the page was loaded from cache (back button)
                loadingModal.hide();

                // Re-enable all buttons when coming back
                buttons.forEach(button => {
                    button.classList.remove('disabled');
                });
            }
        });
    });
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize progress circles
            const circles = document.querySelectorAll('.progress-circle');
            circles.forEach(circle => {
                const progress = circle.getAttribute('data-progress');
                const circumference = 2 * Math.PI * 90; // for r=90
                const strokeDashoffset = circumference - (progress / 100) * circumference;

                // Create SVG element
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('class', 'progress-ring');
                svg.setAttribute('width', '200');
                svg.setAttribute('height', '200');

                // Create a circle for the background with the color rgb(16, 17, 18)
                const backgroundCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                backgroundCircle.setAttribute('cx', '100');  // Center X coordinate
                backgroundCircle.setAttribute('cy', '100');  // Center Y coordinate
                backgroundCircle.setAttribute('r', '90');    // Radius
                backgroundCircle.setAttribute('fill', 'rgb(16, 17, 18)');  // Background color

                // Append the background circle to the SVG
                svg.appendChild(backgroundCircle);

                // Now you can add other elements (e.g., a progress circle) on top of the background
                // Create a progress circle (example)
                const progressCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                progressCircle.setAttribute('cx', '100');
                progressCircle.setAttribute('cy', '100');
                progressCircle.setAttribute('r', '90');
                progressCircle.setAttribute('fill', 'none');
                progressCircle.setAttribute('stroke', 'rgb(16, 17, 18)');
                progressCircle.setAttribute('stroke-width', '20');

                // Append the progress circle
                svg.appendChild(progressCircle);

                // Add the SVG to the document (e.g., append it to the body or a specific element)
                document.body.appendChild(svg);

                const circleElement = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circleElement.setAttribute('class', 'progress-ring__circle');
                circleElement.setAttribute('stroke', '#22d3ee');
                circleElement.setAttribute('stroke-width', '4');
                circleElement.setAttribute('fill', 'transparent');
                circleElement.setAttribute('r', '90');
                circleElement.setAttribute('cx', '100');
                circleElement.setAttribute('cy', '100');
                circleElement.style.strokeDasharray = `${circumference} ${circumference}`;
                circleElement.style.strokeDashoffset = strokeDashoffset;

                svg.appendChild(circleElement);
                circle.insertBefore(svg, circle.firstChild);
            });
        });

    // Function to show/hide the reaction dropdown
function showReactions(button, employeeId) {
    const menu = document.getElementById('reaction-menu-' + employeeId);
    menu.classList.toggle('show');
}

// Function to save a reaction
function selectReaction(reaction, employeeId) {
    fetch('save_reaction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            employee_id: employeeId,
            reaction: reaction
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Reaction saved: ' + reaction);
        } else {
            alert('Failed to save reaction');
        }
    })
    .catch(error => console.error('Error:', error));
}

// Function to open the comment modal
function openCommentModal(employeeId) {
    const modal = document.getElementById('commentModal');
    modal.style.display = 'block';

    // Add employee ID to the modal for reference
    modal.setAttribute('data-employee-id', employeeId);

    // Fetch and display existing comments
    fetchComments(employeeId);
}

// Function to fetch and display comments
function fetchComments(employeeId) {
    fetch('fetch_comments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ employee_id: employeeId })
    })
    .then(response => response.json())
    .then(data => {
        const commentList = document.querySelector('.modal-body .comment-list');
        commentList.innerHTML = ''; // Clear existing comments
        data.comments.forEach(comment => {
            const newComment = document.createElement('div');
            newComment.textContent = `${comment.username} (${comment.comment_time}): ${comment.comment}`;
            newComment.classList.add('comment');
            commentList.appendChild(newComment); // Add new comment at the bottom
        });
    })
    .catch(error => console.error('Error:', error));
}

// Function to post a comment
function postComment(event, input) {
    if (event.key === 'Enter' && input.value.trim() !== '') {
        const employeeId = document.getElementById('commentModal').getAttribute('data-employee-id');
        const comment = input.value.trim();
        const commentTime = new Date().toLocaleString(); // Get current time

        fetch('save_comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                employee_id: employeeId,
                comment: comment,
                comment_time: commentTime
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Add the comment to the UI
                const commentList = document.querySelector('.modal-body .comment-list');
                const newComment = document.createElement('div');
                newComment.textContent = `${data.username} (${commentTime}): ${comment}`;
                newComment.classList.add('comment');
                commentList.appendChild(newComment); // Add new comment at the bottom

                input.value = ''; // Clear the input field

                // Scroll to the bottom to show the new comment
                commentList.scrollTop = commentList.scrollHeight;
            } else {
                alert('Failed to save comment');
            }
        })
        .catch(error => console.error('Error:', error));
    }
}


 //Function to open the comment modal with animation
function openCommentModal(employeeId) {
    const modal = document.getElementById('commentModal');
    modal.style.display = 'block';

    // Add employee ID to the modal for reference
    modal.setAttribute('data-employee-id', employeeId);

    // Fetch and display existing comments
    fetchComments(employeeId);

    // Trigger the animation
    modal.style.animation = 'slideUpFromBottom 0.8s forwards';
}

// Function to close the comment modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'none';
}

// Function to close the reaction dropdown
function closeReactions(employeeId) {
    const menu = document.getElementById('reaction-menu-' + employeeId);
    menu.classList.remove('show');
}

        function getStarRating($scorePercentage) {
            // Max stars are 6, calculate the number of stars based on percentage
            return Math.round(($scorePercentage / 100) * 6);
        }

        let currentCategoryIndex = 1;
const totalCategories = 6; // Assuming there are 6 categories (5 criteria + all employees)
let currentEmployeeIndex = {};

function showNextCategory() {
    // Hide the current category
    document.getElementById(`category-${currentCategoryIndex}`).style.display = 'none';

    // Update to the next category index, loop back to 1 if at the end
    currentCategoryIndex = (currentCategoryIndex % totalCategories) + 1;

    // Show the next category
    document.getElementById(`category-${currentCategoryIndex}`).style.display = 'block';
}

function showPreviousCategory() {
    // Hide the current category
    document.getElementById(`category-${currentCategoryIndex}`).style.display = 'none';

    // Update to the previous category index, loop back to totalCategories if at the start
    currentCategoryIndex = (currentCategoryIndex - 1) || totalCategories;

    // Show the previous category
    document.getElementById(`category-${currentCategoryIndex}`).style.display = 'block';
}

function showNextEmployee(categoryIndex) {
    const totalEmployees = document.querySelectorAll(`#category-${categoryIndex} .employee-card`).length;
    if (!currentEmployeeIndex[categoryIndex]) {
        currentEmployeeIndex[categoryIndex] = 1;
    }

    document.getElementById(`employee-${categoryIndex}-${currentEmployeeIndex[categoryIndex]}`).style.display = 'none';
    currentEmployeeIndex[categoryIndex] = (currentEmployeeIndex[categoryIndex] % totalEmployees) + 1;
    document.getElementById(`employee-${categoryIndex}-${currentEmployeeIndex[categoryIndex]}`).style.display = 'block';
}

function showPreviousEmployee(categoryIndex) {
    const totalEmployees = document.querySelectorAll(`#category-${categoryIndex} .employee-card`).length;
    if (!currentEmployeeIndex[categoryIndex]) {
        currentEmployeeIndex[categoryIndex] = 1;
    }

    document.getElementById(`employee-${categoryIndex}-${currentEmployeeIndex[categoryIndex]}`).style.display = 'none';
    currentEmployeeIndex[categoryIndex] = (currentEmployeeIndex[categoryIndex] - 1) || totalEmployees;
    document.getElementById(`employee-${categoryIndex}-${currentEmployeeIndex[categoryIndex]}`).style.display = 'block';
}

function fetchComments(employeeId) {
    fetch('fetch_comments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ employee_id: employeeId })
    })
    .then(response => response.json())
    .then(data => {
        const commentList = document.querySelector('.modal-body .comment-list');
        commentList.innerHTML = ''; // Clear existing comments
        data.comments.forEach(comment => {
            const newComment = document.createElement('div');
            newComment.textContent = `${comment.username} (${comment.comment_time}): ${comment.comment}`;
            newComment.classList.add('comment');
            commentList.appendChild(newComment); // Add new comment at the bottom
        });
    })
    .catch(error => console.error('Error:', error));
}

function postComment(event, input) {
    if (event.key === 'Enter' && input.value.trim() !== '') {
        const employeeId = document.getElementById('commentModal').getAttribute('data-employee-id');
        const comment = input.value.trim();
        const commentTime = new Date().toLocaleString(); // Get current time

        fetch('save_comments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                employee_id: employeeId,
                comment: comment,
                comment_time: commentTime
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Add the comment to the UI
                const commentList = document.querySelector('.modal-body .comment-list');
                const newComment = document.createElement('div');
                newComment.textContent = `${data.username} (${commentTime}): ${comment}`;
                newComment.classList.add('comment');
                commentList.appendChild(newComment); // Add new comment at the bottom

                input.value = ''; // Clear the input field

                // Scroll to the bottom to show the new comment
                commentList.scrollTop = commentList.scrollHeight;
            } else {
                alert('Failed to save comment');
            }
        })
        .catch(error => console.error('Error:', error));
    }
}


function showPreviousEmployee(categoryIndex) {
    const totalEmployees = document.querySelectorAll(`#category-${categoryIndex} .employee-card`).length;
    if (!currentEmployeeIndex[categoryIndex]) {
        currentEmployeeIndex[categoryIndex] = 1;
    }

    document.getElementById(`employee-${categoryIndex}-${currentEmployeeIndex[categoryIndex]}`).style.display = 'none';
    currentEmployeeIndex[categoryIndex] = (currentEmployeeIndex[categoryIndex] - 1) || totalEmployees;
    document.getElementById(`employee-${categoryIndex}-${currentEmployeeIndex[categoryIndex]}`).style.display = 'block';
}

function autoDisplayEmployees(categoryIndex) {
    setInterval(() => {
        showNextEmployee(categoryIndex);
    }, 15000); // Change every 5 seconds
}

window.onload = function() {
    // Show the first category and first employee immediately
    document.getElementById(`category-1`).style.display = 'block';
    document.getElementById(`employee-1-1`).style.display = 'block';

    // Start the slideshow after showing the first category
    setInterval(showNextCategory, 30000); // Change every 30 seconds

    // Auto display employees for each category
    for (let i = 1; i <= totalCategories; i++) {
        autoDisplayEmployees(i);
    }

    // Auto display all employees
    document.getElementById(`employee-all-1`).style.display = 'block';
   
};


        document.addEventListener('DOMContentLoaded', function () {
    const commentList = document.querySelector('.comment-list');

    commentList.addEventListener('mouseenter', function () {
        commentList.classList.add('scrolling');
    });

    commentList.addEventListener('mouseleave', function () {
        commentList.classList.remove('scrolling');
    });

    commentList.addEventListener('scroll', function () {
        if (commentList.scrollTop + commentList.clientHeight >= commentList.scrollHeight) {
            // Load more comments if needed
            console.log('Reached the bottom of the comment list');
        }
    });
});


    </script>

  <!-- Comment Modal -->
<div id="commentModal" class="modal-right">
    <div class="modal-header">
        <h5>Write a Comment</h5>
        <button class="close-btn" onclick="closeModal('commentModal')">&times;</button>
    </div>
    <div class="modal-body">
        <div class="comment-input-container">
            <input type="text" class="comment-input" placeholder="Write your comment..." onkeypress="postComment(event, this)">
            <button class="btn btn-primary" onclick="postComment(event, document.querySelector('.comment-input'))">Post</button>
        </div>
        <div class="comment-list"></div> <!-- Container to display comments -->
    </div>
</div>


    <!-- Reaction Modal -->
    <div id="reactionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('reactionModal')">&times;</span>
            <div class="modal-body"></div>
        </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/employee.js"></script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
