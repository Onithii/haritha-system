<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure only logged-in Citizens (Role 1) can access this page

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$error = "";
$success = "";

// 2. Fetch complaint categories dynamically from the table
$categories = [];
$cat_query = "SELECT category_id, category_name FROM complaint_categories ORDER BY category_id ASC";
$cat_result = mysqli_query($conn, $cat_query);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// 3. Process Form Submission
if (isset($_POST['submit_complaint'])) {
    $title                = trim($_POST['title']);
    $category_id          = intval($_POST['category_id']);
    $description          = trim($_POST['description']);
    $location_description = trim($_POST['location_description']);
    
    // Optional coordinates defaults
    $latitude  = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    
    // Initial Complaint Status: Pending (assuming 1 is 'Pending' inside your statuses table)
    $status_id = 1; 
    $image_path = null;

    // Validate fundamental strings
    if (empty($title) || empty($description) || empty($category_id)) {
        $error = "Title, Category, and Description are required fields.";
    } else {
        
        // Handle Optional Image Upload
        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] == 0) {
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_name = $_FILES['complaint_image']['name'];
            $file_tmp  = $_FILES['complaint_image']['tmp_name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_extensions)) {
                // Ensure a unique name for each image file to avoid conflicts
                $new_file_name = "IMG_" . uniqid() . "." . $file_ext;
                $upload_dir = "../uploads/";
                
                // Create directory if it doesn't exist yet
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    $image_path = "uploads/" . $new_file_name;
                } else {
                    $error = "Failed to upload the image.";
                }
            } else {
                $error = "Invalid file extension. Only JPG, JPEG, and PNG are allowed.";
            }
        }

        // 4. Save Record to Database via Secure Prepared Statement
        if (empty($error)) {
            $sql = "INSERT INTO complaints (
                        citizen_id, category_id, status_id, title, 
                        description, image_path, latitude, longitude, 
                        location_description
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt, 
                    "iiissssss", 
                    $citizen_id, $category_id, $status_id, $title, 
                    $description, $image_path, $latitude, $longitude, 
                    $location_description
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success = "Your complaint has been submitted successfully!";
                } else {
                    $error = "Database Error: Could not save your complaint.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Database Error: Statement compilation failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Environmental Complaint</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>

        #map {
            width: 100%;
            max-width: 600px; /* Limits how wide the map gets */
            height: 400px;    /* Sets a fixed height */
            margin: 20px 0;   /* Adds spacing around the map container */
            border-radius: 8px; /* Optional: rounds the corners slightly */
            box-shadow: 0px 2px 8px gray; /* Optional: matches your theme shadow */
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f2f5f2;
            margin: 0;
            padding: 20px;
        }

        .form-container {
            max-width: 600px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 2px 8px gray;
        }

        h2 {
            text-align: center;
            color: #2e7d32;
            margin-bottom: 25px;
        }

        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 5px solid;
        }
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border-left-color: #e53935;
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left-color: #4caf50;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        input[type="text"], 
        select, 
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            height: 120px;
        }

        .geo-group {
            display: flex;
            gap: 15px;
        }
        .geo-group .form-group {
            flex: 1;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }

        button:hover {
            background: #1b5e20;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2e7d32;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div id = "map"></div>

<div class="form-container">
    <h2>Report Environmental Issue</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        
        <div class="form-group">
            <label for="title">Complaint Title</label>
            <input type="text" id="title" name="title" placeholder="Brief title of the hazard" required>
        </div>

        <div class="form-group">
            <label for="category_id">Issue Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- Select a Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Detailed Description</label>
            <textarea id="description" name="description" placeholder="Describe the environmental issue and its impact..." required></textarea>
        </div>

        <div class="form-group">
            <label for="complaint_image">Upload Photo Evidence (Optional)</label>
            <input type="file" id="complaint_image" name="complaint_image" accept="image/*">
        </div>

        <div class="form-group">
            <label for="location_description">Location Landmarks / Address</label>
            <input type="text" id="location_description" name="location_description" placeholder="e.g., Near the 4th milestone, opposite the school">
        </div>

        <div class="geo-group">
            <div class="form-group">
                <label for="latitude">Latitude (Optional)</label>
                <input type="text" id="latitude" name="latitude" placeholder="e.g., 6.9271">
            </div>
            <div class="form-group">
                <label for="longitude">Longitude (Optional)</label>
                <input type="text" id="longitude" name="longitude" placeholder="e.g., 79.8612">
            </div>
        </div>

        <button type="submit" name="submit_complaint">Submit Report</button>
    </form>

    <a href="citizen_dash.php" class="back-link">← Return to Dashboard</a>
</div>

<script>
        var map = L.map('map').setView([0, 0], 1);
        L.tileLayer('https://api.maptiler.com/maps/streets-v4/{z}/{x}/{y}.png?key=ZnPljaWSmAXFM3VkUSuc', {
            attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>',
        }).addTo(map);

    </script>

</body>
</html>