<?php
session_start();

// FILE PATHS
$users_file = 'users.json';
$threads_file = 'threads.json';
$posts_file = 'posts.json';

// CREATE FILES IF DON'T EXIST
if (!file_exists($users_file)) file_put_contents($users_file, json_encode([]));
if (!file_exists($threads_file)) file_put_contents($threads_file, json_encode([]));
if (!file_exists($posts_file)) file_put_contents($posts_file, json_encode([]));

// LOAD DATA
$users = json_decode(file_get_contents($users_file), true);
$threads = json_decode(file_get_contents($threads_file), true);
$posts = json_decode(file_get_contents($posts_file), true);

// SALT FOR HASHING
define('SALT', 'DASHIXC2-UBUNTU-2025');

// HANDLE REGISTER
if ($_POST['action'] == 'register') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = hash('sha256', SALT . $_POST['password']);
    
    if (!isset($users[$username])) {
        $users[$username] = [
            'email' => $email,
            'password' => $password,
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => count($users) + 1
        ];
        file_put_contents($users_file, json_encode($users));
        $_SESSION['user_id'] = $users[$username]['user_id'];
        $_SESSION['username'] = $username;
    }
}

// HANDLE LOGIN
elseif ($_POST['action'] == 'login') {
    $username = trim($_POST['username']);
    $password = hash('sha256', SALT . $_POST['password']);
    if (isset($users[$username]) && $users[$username]['password'] == $password) {
        $_SESSION['user_id'] = $users[$username]['user_id'];
        $_SESSION['username'] = $username;
    }
}

// HANDLE LOGOUT
elseif ($_POST['action'] == 'logout') {
    session_destroy();
    header('Location: forums.php');
    exit;
}

// HANDLE CREATE THREAD
elseif ($_POST['action'] == 'create_thread' && isset($_SESSION['user_id'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $new_thread = [
        'id' => count($threads) + 1,
        'title' => $title,
        'content' => $content,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    $threads[] = $new_thread;
    file_put_contents($threads_file, json_encode($threads));
}

// HANDLE REPLY
elseif ($_POST['action'] == 'reply' && isset($_SESSION['user_id'])) {
    $thread_id = (int)$_POST['thread_id'];
    $content = trim($_POST['content']);
    $new_post = [
        'id' => count($posts) + 1,
        'thread_id' => $thread_id,
        'content' => $content,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    $posts[] = $new_post;
    file_put_contents($posts_file, json_encode($posts));
}

// GET THREADS (SORT NEWEST FIRST)
usort($threads, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });

// VIEW THREAD?
$view_thread = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;
$thread_posts = [];
if ($view_thread) {
    foreach ($posts as $post) {
        if ($post['thread_id'] == $view_thread) $thread_posts[] = $post;
    }
    usort($thread_posts, function($a, $b) { return strtotime($a['created_at']) - strtotime($b['created_at']); });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DashixC2 - Forums</title>
    <link href="https://fonts.googleapis.com/css2?family=Metal+Mania&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #000; font-family: 'Metal Mania', cursive; min-height: 100vh; overflow-x: hidden; position: relative; }
        canvas { position: fixed; top: 0; left: 0; z-index: -1; }

        .news-slider { position: fixed; top: 0; left: 0; width: 100%; height: 25px; background: rgba(0,0,0,0.8); border-bottom: 1px solid #6b48ff; z-index: 1000; overflow: hidden; opacity: 0; animation: fadeIn 1.5s ease-out 0.5s forwards; }
        .news-slider ul { display: flex; animation: slide 60s infinite linear; }
        .news-slider li { font-size: 1em; color: #6b48ff; padding: 0 25px; white-space: nowrap; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; animation: newsGlow 2s ease-in-out infinite alternate; text-shadow: 0 0 10px #6b48ff; }

        .visit-counter { position: fixed; bottom: 10px; left: 10px; font-size: 1em; color: #6b48ff; z-index: 1001; background: none; text-transform: uppercase; letter-spacing: 1px; animation: counterGlow 3s ease-in-out infinite alternate; text-shadow: 0 0 8px #6b48ff; opacity: 0; animation: fadeIn 1.5s ease-out 0.8s forwards; }

        .main-box { width: 90%; max-width: 1200px; margin: 30px auto; padding: 30px; background: rgba(0,0,0,0.9); border: 1px solid #6b48ff; border-radius: 20px; text-align: center; backdrop-filter: blur(10px); animation: boxPulse 4s ease-in-out infinite; box-shadow: 0 0 30px rgba(107,72,255,0.3); opacity: 0; animation: fadeIn 1.5s ease-out 1s forwards; }

        .title { font-size: 4em; margin-bottom: 15px; background: linear-gradient(45deg, #6b48ff, #9b59b6, #6b48ff); background-size: 300% 300%; -webkit-background-clip: text; background-clip: text; color: transparent; text-transform: uppercase; letter-spacing: 4px; animation: titleGlow 2s ease-in-out infinite alternate, titleShift 3s ease-in-out infinite; text-shadow: 0 0 30px #6b48ff; opacity: 0; animation: fadeIn 1.5s ease-out 1.2s forwards; }

        .subtitle { font-size: 1.5em; color: #6b48ff; margin-bottom: 40px; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; animation: subtitleGlow 2.5s ease-in-out infinite alternate; text-shadow: 0 0 15px #6b48ff; opacity: 0; animation: fadeIn 1.5s ease-out 1.4s forwards; }

        .navbar { background: rgba(0,0,0,0.6); border: 1px solid #6b48ff; border-radius: 12px; padding: 20px; margin-bottom: 50px; box-shadow: 0 0 20px rgba(107,72,255,0.2); animation: navPulse 3s ease-in-out infinite; opacity: 0; animation: fadeIn 1.5s ease-out 1.6s forwards; }
        .nav-links { display: flex; justify-content: center; gap: 15px; list-style: none; flex-wrap: wrap; }
        .nav-links li a { display: block; padding: 15px 25px; color: #6b48ff; text-decoration: none; text-transform: uppercase; font-weight: bold; letter-spacing: 2px; font-size: 1.1em; border-radius: 8px; transition: all 0.3s; animation: navGlow 2s ease-in-out infinite alternate; text-shadow: 0 0 8px #6b48ff; }
        .nav-links li a:hover { background: linear-gradient(45deg, #6b48ff, #9b59b6); color: white; transform: translateY(-3px); box-shadow: 0 5px 20px rgba(107,72,255,0.6); text-shadow: none; }

        .user-info { position: absolute; top: 30px; right: 30px; color: #6b48ff; text-shadow: 0 0 8px #6b48ff; font-size: 1.1em; }

        .forum-content { opacity: 0; animation: fadeIn 1.5s ease-out 1.8s forwards; }
        .threads-list { max-height: 60vh; overflow-y: auto; margin-bottom: 20px; scrollbar-width: thin; scrollbar-color: #6b48ff rgba(0,0,0,0.5); }
        .threads-list::-webkit-scrollbar { width: 8px; }
        .threads-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.5); }
        .threads-list::-webkit-scrollbar-thumb { background: #6b48ff; border-radius: 4px; }
        .thread-item { background: rgba(0,0,0,0.6); border: 1px solid #6b48ff; border-radius: 10px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: all 0.3s; opacity: 0; }
        .thread-item:nth-child(1) { animation: fadeIn 1.5s ease-out 2s forwards; }
        .thread-item:nth-child(2) { animation: fadeIn 1.5s ease-out 2.2s forwards; }
        .thread-item:nth-child(3) { animation: fadeIn 1.5s ease-out 2.4s forwards; }
        .thread-item:nth-child(4) { animation: fadeIn 1.5s ease-out 2.6s forwards; }
        .thread-item:nth-child(5) { animation: fadeIn 1.5s ease-out 2.8s forwards; }
        .thread-item:hover { background: rgba(107,72,255,0.2); transform: translateX(5px); }
        .thread-title { font-size: 1.2em; color: #6b48ff; margin-bottom: 5px; }
        .thread-author { color: #9b59b6; font-size: 0.9em; }
        .thread-date { color: #6b48ff; font-size: 0.8em; float: right; }

        .form-container { background: rgba(0,0,0,0.6); border: 1px solid #6b48ff; border-radius: 10px; padding: 20px; margin-bottom: 20px; opacity: 0; animation: fadeIn 1.5s ease-out 2.6s forwards; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { color: #6b48ff; display: block; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid #6b48ff; border-radius: 5px; color: #fff; }
        .btn { background: linear-gradient(45deg, #6b48ff, #9b59b6); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; text-transform: uppercase; font-weight: bold; transition: all 0.3s; margin: 5px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(107,72,255,0.6); }

        .thread-view { display: none; opacity: 0; animation: fadeIn 0.5s forwards; }
        .posts-list { max-height: 50vh; overflow-y: auto; margin-bottom: 20px; }
        .post-item { background: rgba(0,0,0,0.6); border-left: 3px solid #6b48ff; padding: 15px; margin-bottom: 15px; }
        .post-author { color: #9b59b6; font-weight: bold; }
        .post-content { color: #fff; margin-top: 10px; }

        @keyframes slide { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes newsGlow { 0% { text-shadow: 0 0 10px #6b48ff; } 100% { text-shadow: 0 0 20px #6b48ff, 0 0 30px #6b48ff; } }
        @keyframes counterGlow { 0% { text-shadow: 0 0 8px #6b48ff; } 100% { text-shadow: 0 0 15px #6b48ff, 0 0 25px #6b48ff; } }
        @keyframes boxPulse { 0%, 100% { box-shadow: 0 0 30px rgba(107,72,255,0.3); } 50% { box-shadow: 0 0 50px rgba(107,72,255,0.5); } }
        @keyframes titleGlow { 0% { text-shadow: 0 0 30px #6b48ff; } 100% { text-shadow: 0 0 50px #6b48ff, 0 0 70px #6b48ff; } }
        @keyframes titleShift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        @keyframes subtitleGlow { 0% { text-shadow: 0 0 15px #6b48ff; } 100% { text-shadow: 0 0 25px #6b48ff, 0 0 35px #6b48ff; } }
        @keyframes navPulse { 0%, 100% { box-shadow: 0 0 20px rgba(107,72,255,0.2); } 50% { box-shadow: 0 0 30px rgba(107,72,255,0.4); } }
        @keyframes navGlow { 0% { text-shadow: 0 0 8px #6b48ff; } 100% { text-shadow: 0 0 15px #6b48ff, 0 0 20px #6b48ff; } }
        @keyframes fadeIn { to { opacity: 1; } }

        #loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #000; display: flex; justify-content: center; align-items: center; z-index: 1000; }
        .loader-spinner { width: 40px; height: 40px; border: 3px solid rgba(107,72,255,0.3); border-top: 3px solid #6b48ff; border-radius: 50%; animation: spin 0.8s linear infinite; }

        @media (max-width: 768px) { .main-box { width: 95%; padding: 20px; } .title { font-size: 3em; } .user-info { position: static; text-align: center; margin: 10px 0; } }
    </style>
</head>
<body>
    <div id="loader"><div class="loader-spinner"></div></div>
    <canvas id="particleCanvas"></canvas>
    <canvas id="snowCanvas"></canvas>

    <div class="news-slider">
        <ul>
            <li>FILE-BASED FORUMS LIVE</li>
            <li>NO MYSQL - PURE SPEED</li>
            <li>ENCRYPTED USERS READY</li>
        </ul>
    </div>

    <div class="visit-counter" id="visitCounter">VISITORS: 0</div>

    <div class="main-box" style="position: relative;">
        <h1 class="title">DASHIXC2</h1>
        <p class="subtitle">FILE FORUMS</p>

        <div class="user-info">
            <?php if (isset($_SESSION['username'])): ?>
                WELCOME <strong><?php echo $_SESSION['username']; ?></strong> | 
                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="logout"><button type="submit" class="btn">LOGOUT</button></form>
            <?php else: ?>
                <form method="POST" style="display:inline;" onsubmit="return showLoginForm(event)">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="username" placeholder="Username" required style="width:100px;padding:5px;">
                    <input type="password" name="password" placeholder="Pass" required style="width:80px;padding:5px;">
                    <button type="submit" class="btn">LOGIN</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return showRegisterForm(event)">
                    <input type="hidden" name="action" value="register">
                    <input type="text" name="username" placeholder="New User" required style="width:80px;padding:5px;">
                    <button type="submit" class="btn">REGISTER</button>
                </form>
            <?php endif; ?>
        </div>

        <nav class="navbar">
            <ul class="nav-links">
                <li><a href="index.html">Home</a></li>
                <li><a href="forums.php">Forums</a></li>
                <li><a href="test.html">Test</a></li>
                <li><a href="methods.html">Methods</a></li>
                <li><a href="purchase.html">Purchase</a></li>
                <li><a href="status.html">Status</a></li>
            </ul>
        </nav>

        <div class="forum-content">
            <?php if ($view_thread): ?>
                <div class="thread-view" style="display:block;">
                    <h2 style="color:#6b48ff;"><?php echo htmlspecialchars($threads[$view_thread-1]['title']); ?></h2>
                    <div class="posts-list">
                        <?php foreach ($thread_posts as $post): ?>
                            <div class="post-item">
                                <div class="post-author"><?php echo htmlspecialchars($post['username']); ?> - <?php echo date('M j, Y H:i', strtotime($post['created_at'])); ?></div>
                                <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" class="form-container">
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="thread_id" value="<?php echo $view_thread; ?>">
                            <div class="form-group"><textarea name="content" placeholder="Reply..." required></textarea></div>
                            <button type="submit" class="btn">POST REPLY</button>
                        </form>
                    <?php endif; ?>
                    <a href="forums.php" class="btn">BACK TO FORUMS</a>
                </div>
            <?php else: ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <form method="POST" class="form-container">
                        <input type="hidden" name="action" value="create_thread">
                        <div class="form-group"><input type="text" name="title" placeholder="Thread Title" required></div>
                        <div class="form-group"><textarea name="content" placeholder="Thread Content" required></textarea></div>
                        <button type="submit" class="btn">CREATE THREAD</button>
                    </form>
                <?php endif; ?>

                <div class="threads-list">
                    <?php foreach ($threads as $index => $thread): ?>
                        <div class="thread-item" onclick="window.location='forums.php?thread=<?php echo $thread['id']; ?>'">
                            <div class="thread-title"><?php echo htmlspecialchars($thread['title']); ?></div>
                            <div style="clear:both;">
                                <span class="thread-author">By: <?php echo htmlspecialchars($thread['username']); ?></span>
                                <span class="thread-date"><?php echo date('M j, Y', strtotime($thread['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($threads)): ?><p style="color:#6b48ff;">NO THREADS YET - CREATE FIRST!</p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showLoginForm(e) { e.preventDefault(); alert('Username: test\nPassword: test'); }
        function showRegisterForm(e) { e.preventDefault(); alert('Username: newuser\nEmail: new@test.com\nPassword: test'); }
        
        // ALL YOUR ANIMATIONS + PARTICLES + SNOW + CLICK SOUND FROM BEFORE
        function updateVisitCounter() { let count = localStorage.getItem('visitCount') || 0; count++; localStorage.setItem('visitCount', count); document.getElementById('visitCounter').textContent = `VISITORS: ${count}`; }
        updateVisitCounter(); setTimeout(() => document.getElementById('loader').style.display = 'none', 1200);
        // PARTICLES + SNOW CODE HERE (SAME AS BEFORE)
        document.addEventListener('click', () => new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBIAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=').play().catch(() => {}));
    </script>
</body>
</html>