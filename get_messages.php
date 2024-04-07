<?php
$isLoggedIn = isset($_COOKIE['user']);
if ($isLoggedIn)
  {
    $host = 'localhost';
    $dbUser = 'root';
    $dbPassword = 'root';
    $dbName = 'social_network';

    $conn = new mysqli($host, $dbUser, $dbPassword, $dbName);

    if ($conn->connect_error)
      {
        die("Connection failed: " . $conn->connect_error);
      }

    if (isset($_GET['chat_id']) && !empty($_GET['chat_id']))
      {
        $chatId = $_GET['chat_id'];
        $profileId = isset($_GET['id']) ? $_GET['id'] : ($isLoggedIn ? $_COOKIE['user'] : null);

        if ($profileId)
          {
            $checkAccessQuery = "SELECT id FROM chats WHERE id = ? AND (user1_id = ? OR user2_id = ?)";
            $checkAccessStmt = $conn->prepare($checkAccessQuery);
            $checkAccessStmt->bind_param("iii", $chatId, $profileId, $profileId);
            $checkAccessStmt->execute();
            $accessResult = $checkAccessStmt->get_result();

            if ($accessResult->num_rows == 0)
              {

                echo json_encode(['error' => 'Access denied']);
                exit;
              }

            $query = "SELECT messages.*, users.username FROM messages 
              JOIN users ON users.id = messages.sender_id 
              WHERE chat_id = ? ORDER BY sent_at ASC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $chatId);
            $stmt->execute();
            $result = $stmt->get_result();
            $messages = [];
            while ($row = $result->fetch_assoc())
              {
                $messages[] = $row;
              }
            echo json_encode($messages);
          }
        else
          {
            echo json_encode([]);
          }
      }
    $conn->close();
  }
?>
