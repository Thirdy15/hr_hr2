<?php
include '../../db/db_conn.php'; // Include database connection

// Fetch all employees
$query = "SELECT * FROM employee_register";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Schedule</title>
    <style>
        body {
            background-color: #1a1a1a; /* Dark background for the entire page */
            color: #ffffff; /* White text color for better contrast */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Modern font */
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #4CAF50; /* Green color for the heading */
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #2d2d2d; /* Dark background for the table */
            color: #ffffff; /* White text color for table content */
            border-radius: 10px; /* Rounded corners for the table */
            overflow: hidden; /* Ensures rounded corners are visible */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3); /* Subtle shadow for depth */
        }

        table, th, td {
            border: 1px solid #444; /* Darker border color */
        }

        th, td {
            padding: 12px;
            text-align: center;
        }

        th {
            background: linear-gradient(135deg, #4CAF50, #45a049); /* Gradient background for headers */
            color: white; /* White text color for table headers */
            font-weight: bold;
        }

        td {
            background-color: #3d3d3d; /* Slightly lighter dark background for table cells */
        }

        tr:hover {
            background-color: #4CAF50; /* Green hover effect for rows */
            color: white; /* White text on hover */
            transition: background-color 0.3s ease; /* Smooth transition */
        }

        .edit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 4px; /* Rounded corners for the button */
            font-size: 14px;
            transition: background-color 0.3s ease; /* Smooth hover transition */
        }

        .edit-btn:hover {
            background-color: #45a049; /* Darker green on hover */
        }

        /* Modal styling */
        #editModal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #2d2d2d; /* Dark background for the modal */
            padding: 30px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.7);
            color: #ffffff; /* White text color for modal content */
            border-radius: 10px; /* Rounded corners for the modal */
            width: 400px; /* Increased width */
            max-width: 90%; /* Ensure it doesn't overflow on small screens */
        }

        #editModal h2 {
            margin-top: 0;
            font-size: 24px;
            text-align: center;
            color: #4CAF50; /* Green color for modal heading */
        }

        #editModal label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #ffffff; /* White text color for labels */
        }

        #editModal input, #editModal select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            background-color: #3d3d3d; /* Dark background for input fields */
            color: #ffffff; /* White text color for input fields */
            border: 1px solid #444; /* Darker border color */
            border-radius: 4px; /* Rounded corners for input fields */
        }

        #editModal button {
            width: 48%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px; /* Rounded corners for buttons */
            font-size: 16px;
            transition: background-color 0.3s ease; /* Smooth hover transition */
        }

        #editModal button:hover {
            background-color: #45a049; /* Darker green on hover */
        }

        #editModal button[type="button"] {
            background-color: #666; /* Gray color for the Cancel button */
        }

        #editModal button[type="button"]:hover {
            background-color: #555; /* Darker gray on hover */
        }
        table, th, td {
    border: 1px solid black; /* Black border for the table */
}
    </style>
</head>
<body>
    <h1>Employee Schedule</h1>
    <table>
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Shift Type</th>
                <th>Schedule Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($employee = $result->fetch_assoc()): ?>
                    <?php
                    // Fetch the employee's schedule
                    $scheduleQuery = "SELECT * FROM employee_schedule WHERE employee_id = ? ORDER BY schedule_date DESC LIMIT 1";
                    $stmt = $conn->prepare($scheduleQuery);
                    $stmt->bind_param('i', $employee['e_id']);
                    $stmt->execute();
                    $scheduleResult = $stmt->get_result();
                    $schedule = $scheduleResult->fetch_assoc();
                    $stmt->close();
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($employee['e_id']); ?></td>
                        <td><?php echo htmlspecialchars($employee['firstname'] . ' ' . $employee['lastname']); ?></td>
                        <td><?php echo htmlspecialchars($schedule['shift_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($schedule['schedule_date'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($schedule['start_time'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($schedule['end_time'] ?? 'N/A'); ?></td>
                        <td>
                            <button class="edit-btn" onclick="openEditModal(<?php echo $employee['e_id']; ?>)">Edit Schedule</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No employees found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Edit Modal -->
    <div id="editModal">
        <h2>Edit Schedule</h2>
        <form id="editForm" method="POST" action="../../employee/supervisor/updateSchedule.php">
            <input type="hidden" id="editEmployeeId" name="employee_id">
            <label for="editShiftType">Shift Type:</label>
            <select id="editShiftType" name="shift_type" required>
                <option value="day">Day Shift</option>
                <option value="night">Night Shift</option>
            </select><br><br>
            <label for="editScheduleDate">Schedule Date:</label>
            <input type="date" id="editScheduleDate" name="schedule_date" required><br><br>
            <label for="editStartTime">Start Time:</label>
            <input type="time" id="editStartTime" name="start_time" required><br><br>
            <label for="editEndTime">End Time:</label>
            <input type="time" id="editEndTime" name="end_time" required><br><br>
            <button type="submit">Save Changes</button>
            <button type="button" onclick="closeEditModal()">Cancel</button>
        </form>
    </div>

    <script>
        // Function to open the edit modal and populate form fields
        function openEditModal(employeeId) {
            fetch(`/HR2/employee_db/supervisor/getSchedule.php?employee_id=${employeeId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('editEmployeeId').value = employeeId;
                    document.getElementById('editShiftType').value = data.shift_type || 'day';
                    document.getElementById('editScheduleDate').value = data.schedule_date || '';
                    document.getElementById('editStartTime').value = data.start_time || '';
                    document.getElementById('editEndTime').value = data.end_time || '';
                    document.getElementById('editModal').style.display = 'block';
                })
                .catch(error => console.error('Error fetching schedule:', error));
        }

        // Function to close the edit modal
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
</body>
</html>