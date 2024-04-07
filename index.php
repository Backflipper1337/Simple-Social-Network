<?php
$host = 'localhost';
$dbUser = 'root';
$dbPassword = 'root';
$dbName = 'social_network';

$conn = new mysqli($host, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error)
  {
    die("Connection failed: " . $conn->connect_error);
  }

function setupDatabase($conn)
  {

    $createUsersTable = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        registration_ip VARCHAR(255) NOT NULL,
        registration_time DATETIME NOT NULL,
        description TEXT
    )";

    $createChatsTable = "CREATE TABLE IF NOT EXISTS chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user1_id INT NOT NULL,
        user2_id INT NOT NULL,
        UNIQUE KEY unique_chat (user1_id, user2_id),
        FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $createMessagesTable = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    if ($conn->query($createUsersTable) === true)
      {
        echo "Table users created successfully or already exists.<br>";
      }
    else
      {
        echo "Error creating table users: " . $conn->error . "<br>";
      }

    if ($conn->query($createChatsTable) === true)
      {
        echo "Table chats created successfully or already exists.<br>";
      }
    else
      {
        echo "Error creating table chats: " . $conn->error . "<br>";
      }

    if ($conn->query($createMessagesTable) === true)
      {
        echo "Table messages created successfully or already exists.<br>";
      }
    else
      {
        echo "Error creating table messages: " . $conn->error . "<br>";
      }
  }

if (isset($_GET['setup']) && $_GET['pwd'] === 'admin')
  {
    setupDatabase($conn);
  }
elseif (!isset($_GET['setup']))
  {

  }
else
  {
    echo "Unauthorized access.";
  }

function registerUser($conn, $username, $password)
  {

    $checkUsername = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkUsername->bind_param("s", $username);
    $checkUsername->execute();
    $result = $checkUsername->get_result();
    if ($result->num_rows > 0)
      {
        echo "Username already exists.";
        return;
      }

    $userIp = $_SERVER['REMOTE_ADDR'];
    $checkIp = $conn->prepare("SELECT registration_time FROM users WHERE registration_ip = ? ORDER BY registration_time DESC LIMIT 1");
    $checkIp->bind_param("s", $userIp);
    $checkIp->execute();
    $result = $checkIp->get_result();
    if ($result->num_rows > 0)
      {
        $lastRegistrationTime = new DateTime($result->fetch_assoc() ['registration_time']);
        $now = new DateTime();
        if ($now->diff($lastRegistrationTime)->h < 1)
          {
            echo "Registration limit exceeded. Please try again later.";
            return;
          }
      }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $register = $conn->prepare("INSERT INTO users (username, password, registration_ip, registration_time) VALUES (?, ?, ?, NOW())");
    $register->bind_param("sss", $username, $hashedPassword, $userIp);
    if ($register->execute())
      {
        echo "User registered successfully.";
      }
    else
      {
        echo "Error: " . $conn->error;
      }
  }

function loginUser($conn, $username, $password)
  {
    $user = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $user->bind_param("s", $username);
    $user->execute();
    $result = $user->get_result();
    if ($result->num_rows > 0)
      {
        $userData = $result->fetch_assoc();
        if (password_verify($password, $userData['password']))
          {

            setcookie("user", $userData['id'], time() + 3600, "/");
            echo "Login successful.";
            return true;
          }
      }
    echo "Invalid username or password.";
    return false;
  }
if (isset($_POST['send_message']) && isset($_POST['message']) && isset($_GET['id']))
  {
    $chatId = $_GET['id'];
    $userId = $_COOKIE['user'];
    $message = $_POST['message'];

    $insertMessage = $conn->prepare("INSERT INTO messages (chat_id, sender_id, message) VALUES (?, ?, ?)");
    $insertMessage->bind_param("iis", $chatId, $userId, $message);
    if ($insertMessage->execute())
      {
        echo "Message sent.";

        header("Location: eznetwork.php?page=chats&id=" . $chatId);
        exit;
      }
    else
      {
        echo "Error.";
      }
  }

if (isset($_POST['register']))
  {
    if (isset($_POST['username'], $_POST['password']))
      {
        registerUser($conn, $_POST['username'], $_POST['password']);
        exit;
      }
    else
      {
        echo "Username and password are required.";
        exit;
      }
  }

if (isset($_POST['login']))
  {
    if (isset($_POST['username'], $_POST['password']))
      {
        if (loginUser($conn, $_POST['username'], $_POST['password']))
          {

          }
        exit;
      }
    else
      {
        echo "Username and password are required for login.";
        exit;
      }
  }

if (isset($_POST['logout']) || isset($_GET['logout']))
  {
    setcookie("user", "", time() - 3600, "/");
    header("Location: eznetwork.php");
    exit;
  }

if (isset($_POST['start_chat']))
  {
    $user1_id = $_COOKIE['user'];
    $user2_id = $_POST['recipient_id'];

    $checkChat = $conn->prepare("SELECT id FROM chats WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $checkChat->bind_param("iiii", $user1_id, $user2_id, $user2_id, $user1_id);
    $checkChat->execute();
    $result = $checkChat->get_result();
    if ($result->num_rows == 0)
      {

        $createChat = $conn->prepare("INSERT INTO chats (user1_id, user2_id) VALUES (?, ?)");
        $createChat->bind_param("ii", $user1_id, $user2_id);
        $createChat->execute();
      }
    header("Location: eznetwork.php?page=chats");
    exit;
  }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['description']))
  {
    $description = $_POST['description'];
    $userId = $_COOKIE['user'];
    $update = $conn->prepare("UPDATE users SET description = ? WHERE id = ?");
    $update->bind_param("si", $description, $userId);
    if ($update->execute())
      {
        echo "Updated successfully.";
        header("Refresh:0");
      }
    else
      {
        echo "Error.";
      }
  }
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Social Network</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style> 
    .chat-list li {
    list-style-type: none;
    margin-bottom: 5px;
}

.chat-messages .message {
    margin-bottom: 10px;
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 5px;
}
    </style>
</head>
<body>
<?php
$isLoggedIn = isset($_COOKIE['user']);
if ($isLoggedIn)
  {
    echo '<div class="container">
                <div class="row">
                    <div class="col-3">
                    <div class="list-group">
                    <a href="?page=profile" class="list-group-item list-group-item-action">Profile</a>
                    <a href="?page=chats" class="list-group-item list-group-item-action">Chats</a>
                    <a href="?page=users" class="list-group-item list-group-item-action">Users</a> 
                    <a href="?logout" class="list-group-item list-group-item-action">Logout</a> 
                  </div>
                    </div>
                    <div class="col-9">';
    if (isset($_GET['page']) && $_GET['page'] == 'users')
      {

        $query = "SELECT id, username FROM users ORDER BY username ASC";
        $result = $conn->query($query);

        echo '<div class="container"><h2>Users</h2><ul class="list-unstyled">';
        while ($row = $result->fetch_assoc())
          {
            echo '<li><a href="eznetwork.php?page=profile&id=' . $row['id'] . '">' . htmlspecialchars($row['username']) . '</a></li>';
          }
        echo '</ul></div>';
      }
  }
elseif (isset($_GET['page']))
  {
    echo '<div class="container">
                <div class="row">
                    <div class="col-3">
                        <div class="list-group">
                            <a href="?reg" class="list-group-item list-group-item-action">Registration</a>
                        </div>
                    </div>
                    <div class="col-9">';
  }

if (isset($_GET['page']) && $_GET['page'] == 'profile')
  {
    $profileId = isset($_GET['id']) ? $_GET['id'] : ($isLoggedIn ? $_COOKIE['user'] : null);

    if ($profileId)
      {
        $user = $conn->prepare("SELECT username, description FROM users WHERE id = ?");
        $user->bind_param("i", $profileId);
        $user->execute();
        $result = $user->get_result();
        if ($result->num_rows > 0)
          {
            $userData = $result->fetch_assoc();
            echo "<h3>" . htmlspecialchars($userData['username']) . "</h3>";
            echo "<p>" . htmlspecialchars($userData['description']) . "</p>";

            if ($isLoggedIn && $profileId == $_COOKIE['user'])
              {
                echo '<form action="eznetwork.php?page=profile" method="post">
                        <div class="form-group">
                            <label for="description">Profile description</label>
                            <textarea class="form-control" id="description" name="description" rows="3">' . htmlspecialchars($userData['description']) . '</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                      </form>';
              }
            else
              {

                if ($isLoggedIn && $profileId != $_COOKIE['user'])
                  {
                    echo '<form action="eznetwork.php" method="post">
                            <input type="hidden" name="recipient_id" value="' . $profileId . '">
                            <button type="submit" name="start_chat">Write</button>
                          </form>';
                  }
              }
          }
        else
          {
            echo "Profile not found.";
          }
      }
    else
      {
        echo "You must be logged in to view your profile.";
      }
  }
if ($isLoggedIn)
  {
    $userId = $_COOKIE['user'];
    $user = $conn->prepare("SELECT username, description FROM users WHERE id = ?");
    $user->bind_param("i", $userId);
    $user->execute();
    $result = $user->get_result();
    if ($result->num_rows > 0)
      {
        $userData = $result->fetch_assoc();

        if (isset($_GET['page']) && $_GET['page'] == 'chats')
          {

            echo '<div id="chats">
                    <h2>Chats</h2>';

            $userId = $_COOKIE['user'];
            $query = "SELECT chats.id, user1_id, user2_id, users.username FROM chats 
                              JOIN users ON users.id = chats.user1_id OR users.id = chats.user2_id 
                              WHERE (chats.user1_id = ? OR chats.user2_id = ?) AND users.id != ? 
                              GROUP BY chats.id";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $userId, $userId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            echo '<ul class="chat-list">';
            while ($row = $result->fetch_assoc())
              {
                echo '<li><a href="eznetwork.php?page=chats&id=' . $row['id'] . '">' . htmlspecialchars($row['username']) . '</a></li>';
              }
            echo '</ul>';

          }
        echo '        </div>
                </div>
            </div>';
        if (isset($_GET['page']) && $_GET['page'] == 'chats' && isset($_GET['id']))
          {
            $chatId = $_GET['id'];
            $userId = $_COOKIE['user'];

            $checkAccess = $conn->prepare("SELECT id FROM chats WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
            $checkAccess->bind_param("iii", $chatId, $userId, $userId);
            $checkAccess->execute();
            if ($checkAccess->get_result()->num_rows == 0)
              {
                echo "You do not have access to this chat.";
                return;
              }

            $query = "SELECT messages.*, users.username FROM messages 
                          JOIN users ON users.id = messages.sender_id 
                          WHERE chat_id = ? ORDER BY sent_at ASC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $chatId);
            $stmt->execute();
            $result = $stmt->get_result();

            echo '<div class="chat-messages">';
            while ($row = $result->fetch_assoc())
              {
                echo '<div class="message">' . htmlspecialchars($row['username']) . ': ' . htmlspecialchars($row['message']) . '</div>';
              }
            echo '</div>';
            if (isset($_GET['page']) && $_GET['page'] == 'chats' && isset($_GET['id']))
              {

                echo '<form action="eznetwork.php?page=chats&id=' . $chatId . '" method="post">
                            <textarea name="message" required></textarea>
                            <button type="submit" name="send_message">Send</button>
                          </form>
                          <script>
function fetchMessages() {
    var chatId = ' . json_encode($_GET['id']) . ';
    fetch(\'get_messages.php?chat_id=\' + chatId)
        .then(response => response.json())
        .then(data => {
            var messagesContainer = document.querySelector(\'.chat-messages\');
            messagesContainer.innerHTML = \'\'; 
            data.forEach(function(message) {
                var messageElement = document.createElement(\'div\');
                messageElement.className = \'message\';
                messageElement.textContent = message.username + \': \' + message.message;
                messagesContainer.appendChild(messageElement);
            });
        })
        .catch(error => console.error(\'Error:\', error));
}

setInterval(fetchMessages, 1000); 
</script>';
              }
          }
      }
    else
      {
        setcookie("user", "", time() - 3600, "/");
        header("Location: eznetwork.php");
        exit;
      }
  }
elseif(isset($_GET['page'])){
    exit;
}else
  {
    echo '<div class="container">
        <h2>Sign In</h2>
        <form id="loginForm" method="post">
            <div class="form-group">
                <label for="username">Login</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" name="login">Sign in</button>
        </form>
        <h2>or</h2>
        <h2>Sign Up</h2>
        <form id="registrationForm" method="post">
            <div class="form-group">
                <label for="username">Login</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" name="register">Register</button>
        </form>
    </div>';
  }
?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $('#loginForm').submit(function(e) {
            e.preventDefault();
            $.ajax({
                type: "POST",
                url: "eznetwork.php",
                data: $(this).serialize() + "&login=true",
                success: function(response) {
                    alert(response);
                    if (response.includes("Login successful.")) {
                        window.location.reload();
                    }
                }
            });
        });

        $('#registrationForm').submit(function(e) {
            e.preventDefault();
            $.ajax({
                type: "POST",
                url: "eznetwork.php",
                data: $(this).serialize() + "&register=true",
                success: function(response) {
                    alert(response);
                    if (response.includes("User registered successfully.")) {
                        window.location.reload();
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>
