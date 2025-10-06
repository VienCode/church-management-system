<?php
$mysqli = include 'database.php';
session_start();;

//THIS IS A FUNCTION (FETCHING POST) PHP CODE FOR UPLOAD (CHURCH UPDATES PAGE)
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT id, title, content, image FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($post = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'post' => $post
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Post not found'
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No post ID provided'
    ]);
}
?>