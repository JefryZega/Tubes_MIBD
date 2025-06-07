<?php
session_start();
require_once 'koneksiDB.php';

// Ensure consistent session variable naming
$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : (isset($_SESSION['uid']) ? $_SESSION['uid'] : null);

// Redirect if not logged in
if (!$userId) {
    header("Location: ../Index/login.php");
    exit();
}

// Store consistent session variable
$_SESSION['userId'] = $userId;

// Get video ID from URL
if (!isset($_GET['videoId'])) {
    header("Location: home.php");
    exit();
}
$videoId = $_GET['videoId'];

// Get user channels (for sidebar)
$sqlChannels = "SELECT chnlId, nama, pfp FROM Channel WHERE userId = ?";
$paramsChannels = array($userId);
$stmtChannels = sqlsrv_query($conn, $sqlChannels, $paramsChannels);

$userChannels = [];
if ($stmtChannels !== false) {
    while ($row = sqlsrv_fetch_array($stmtChannels, SQLSRV_FETCH_ASSOC)) {
        $userChannels[] = $row;
    }
}

// Record view if not already viewed
$sqlCheckView = "SELECT 1 FROM [View] WHERE userId = ? AND videoId = ?";
$paramsCheckView = array($userId, $videoId);
$stmtCheckView = sqlsrv_query($conn, $sqlCheckView, $paramsCheckView);

if (sqlsrv_has_rows($stmtCheckView) === false) {
    $sqlInsertView = "INSERT INTO [View] (userId, videoId, durasi, tglView, waktuView)
                      VALUES (?, ?, 0, CONVERT(date, GETDATE()), CONVERT(time, GETDATE()))";
    $paramsInsertView = array($userId, $videoId);
    sqlsrv_query($conn, $sqlInsertView, $paramsInsertView);
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        $sqlInsertComment = "INSERT INTO Komen (userId, videoId, konten, tanggalKomen)
                             VALUES (?, ?, ?, CONVERT(date, GETDATE()))";
        $paramsInsertComment = array($userId, $videoId, $comment);
        sqlsrv_query($conn, $sqlInsertComment, $paramsInsertComment);
    }
}

// Get video details
$sqlVideo = "SELECT v.*, c.nama AS channel_name, c.pfp AS channel_pfp
             FROM Video v
             JOIN Channel c ON v.chnlId = c.chnlId
             WHERE v.videoId = ?";
$paramsVideo = array($videoId);
$stmtVideo = sqlsrv_query($conn, $sqlVideo, $paramsVideo);

if ($stmtVideo === false || !sqlsrv_has_rows($stmtVideo)) {
    header("Location: home.php");
    exit();
}
$video = sqlsrv_fetch_array($stmtVideo, SQLSRV_FETCH_ASSOC);

// Format upload date
$uploadDate = $video['tglUpld']->format('M d, Y');

// Get view count
$sqlViews = "SELECT COUNT(*) AS view_count FROM [View] WHERE videoId = ?";
$paramsViews = array($videoId);
$stmtViews = sqlsrv_query($conn, $sqlViews, $paramsViews);
$viewCount = sqlsrv_fetch_array($stmtViews, SQLSRV_FETCH_ASSOC)['view_count'];

// Get like/dislike counts
$sqlLikes = "SELECT 
                SUM(CASE WHEN tipe = 'like' THEN 1 ELSE 0 END) AS like_count,
                SUM(CASE WHEN tipe = 'dislike' THEN 1 ELSE 0 END) AS dislike_count
             FROM Reaksi
             WHERE videoId = ?";
$paramsLikes = array($videoId);
$stmtLikes = sqlsrv_query($conn, $sqlLikes, $paramsLikes);
$reactions = sqlsrv_fetch_array($stmtLikes, SQLSRV_FETCH_ASSOC);
$likeCount = $reactions['like_count'] ?: 0;
$dislikeCount = $reactions['dislike_count'] ?: 0;

// Get comments
$sqlComments = "SELECT k.*, u.username 
                FROM Komen k
                JOIN [User] u ON k.userId = u.userId
                WHERE k.videoId = ?
                ORDER BY k.tanggalKomen DESC";
$paramsComments = array($videoId);
$stmtComments = sqlsrv_query($conn, $sqlComments, $paramsComments);

$comments = [];
if ($stmtComments !== false) {
    while ($row = sqlsrv_fetch_array($stmtComments, SQLSRV_FETCH_ASSOC)) {
        $comments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($video['judul']) ?> - MeTube</title>
    <link rel="stylesheet" href="../Styles/videoDetail_styles.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.12.1/css/all.min.css" />
    <style>
        /* Add styles from home.php */
        .video_grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .profile_hover_container {
            position: relative;
            display: inline-block;
        }
        
        .logout_dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            background: white;
            min-width: 160px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 4px;
            z-index: 100;
            padding: 10px;
        }
        
        .profile_hover_container:hover .logout_dropdown {
            display: block;
        }
        
        .logout_dropdown a {
            display: block;
            padding: 8px 12px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .logout_dropdown a:hover {
            background: #f0f0f0;
        }
        
        .switch_btn {
            border-bottom: 1px solid #eee;
        }
        
        .channel_pfp {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        /* Video detail specific styles */
        .video_detail_container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .video_player video {
            width: 100%;
            border-radius: 10px;
        }

        .video_info {
            margin-top: 20px;
        }

        .video_title {
            font-size: 24px;
            margin-bottom: 10px;
            color: #222;
        }

        .video_stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #555;
            margin-bottom: 15px;
        }

        .video_actions button {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            margin-left: 10px;
        }

        .video_description {
            font-size: 16px;
            color: #333;
            line-height: 1.6;
            white-space: pre-line;
        }

        .comments_section {
            margin-top: 40px;
        }

        .comments_section h3 {
            margin-bottom: 10px;
            color: #333;
        }

        .comment_input {
            width: 100%;
            height: 80px;
            padding: 10px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #ccc;
            resize: vertical;
        }

        .comment_submit {
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #e63946;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .comment_list {
            margin-top: 20px;
        }

        .comment_item {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .comment_header {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .comment_username {
            font-weight: bold;
            margin-right: 10px;
        }

        .comment_date {
            color: #777;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <input type="checkbox" id="check" />

    <div class="container">
        <!-- Sidebar (same as home.php) -->
        <div class="left_side">
            <div class="menu-header">
                <label for="check">
                    <span class="fas fa-times" id="times"></span>
                    <span class="fas fa-bars" id="bars"></span>
                </label>
                <div class="head">MeTube</div>
            </div>

            <ol>
                <li>
                    <a href="home.php"><i class="fas fa-home"></i>Home</a>
                </li>
                <li>
                    <a href="subs.php"><i class="fas fa-star"></i>Subscription</a>
                </li>
                <li>
                    <a href="notification.php"><i class="fas fa-bell"></i>Notification</a>
                </li>
                <li>
                    <a href="collaboration.php"><i class="fas fa-handshake"></i>Collaboration</a>
                </li>
                
                <?php if (!empty($userChannels)): ?>
                    <?php foreach ($userChannels as $channel): ?>
                    <li>
                        <a href="channelContent.php?chnlId=<?= $channel['chnlId'] ?>">
                            <?php if (!empty($channel['pfp'])): ?>
                                <img src="<?= htmlspecialchars($channel['pfp']) ?>" 
                                     alt="Profile" 
                                     class="channel_pfp"
                                     onerror="this.src='default_pfp.jpg'">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($channel['nama']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <li>
                    <a href="addChannel.php"><i class="fas fa-users"></i>Add Channel</a>
                </li>
            </ol>
        </div>

        <div class="right_side">
            <div class="top_bar">
                <input type="text" placeholder="Search..." class="search_input" />
                <div class="profile_hover_container">
                    <i class="fas fa-user-circle account_icon"></i>
                    <div class="logout_dropdown">
                        <a href="#" class="switch_btn">Switch Account</a>
                        <a href="../Index/login.php" class="logout_btn">Logout</a>
                    </div>
                </div>
            </div>

            <div class="video_detail_container">
                <!-- Video Player -->
                <div class="video_player">
                    <video controls poster="<?= htmlspecialchars($video['thumbnail']) ?>">
                        <source src="<?= htmlspecialchars($video['playback']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>

                <!-- Video Info -->
                <div class="video_info">
                    <h2 class="video_title"><?= htmlspecialchars($video['judul']) ?></h2>
                    <div class="video_stats">
                        <span><?= number_format($viewCount) ?> views • <?= $uploadDate ?></span>
                        <div class="video_actions">
                            <button class="like_btn">👍 <?= number_format($likeCount) ?></button>
                            <button class="dislike_btn">👎 <?= number_format($dislikeCount) ?></button>
                        </div>
                    </div>
                    <p class="video_description"><?= htmlspecialchars($video['desc']) ?></p>
                </div>

                <!-- Comment Section -->
                <div class="comments_section">
                    <h3>Comments (<?= count($comments) ?>)</h3>
                    <form method="POST" action="videoDetail.php?videoId=<?= $videoId ?>">
                        <textarea 
                            name="comment" 
                            class="comment_input" 
                            placeholder="Add a public comment..."
                            required
                        ></textarea>
                        <button type="submit" class="comment_submit">Comment</button>
                    </form>

                    <div class="comment_list">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment_item">
                                <div class="comment_header">
                                    <span class="comment_username"><?= htmlspecialchars($comment['username']) ?></span>
                                    <span class="comment_date"><?= date('M d, Y', strtotime($comment['tanggalKomen']->format('Y-m-d'))) ?></span>
                                </div>
                                <p><?= htmlspecialchars($comment['konten']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add search functionality
        document.querySelector('.search_input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    alert(`Searching for: ${searchTerm}`);
                    // In a real app, you would submit the search form
                }
            }
        });
        
        // Prevent form submission on enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.classList.contains('search_input')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>