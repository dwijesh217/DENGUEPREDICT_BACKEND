<?php
/**
 * ONE-TIME PASSWORD REHASH SCRIPT
 * 
 * Converts existing plain-text passwords in the `users` table to bcrypt hashes.
 * 
 * Usage:
 *   1. Run via browser: http://localhost/denguepredict/rehash_passwords.php
 *   2. Or via CLI:      php rehash_passwords.php
 *   3. DELETE THIS FILE immediately after running.
 * 
 * Safety:
 *   - Skips passwords that are already bcrypt hashed ($2y$ prefix)
 *   - Shows a summary of how many passwords were converted
 */

require_once 'db_config.php';

try {
    $conn = getConnection();
    
    // Fetch all users
    $stmt = $conn->query("SELECT id, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($users);
    $rehashed = 0;
    $skipped = 0;
    
    foreach ($users as $user) {
        // Skip if already a bcrypt hash (starts with $2y$ or $2a$ or $2b$)
        if (preg_match('/^\$2[ayb]\$/', $user['password'])) {
            $skipped++;
            continue;
        }
        
        // Hash the plain-text password
        $hashedPassword = password_hash($user['password'], PASSWORD_BCRYPT);
        
        // Update in database
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->execute([$hashedPassword, $user['id']]);
        
        $rehashed++;
    }
    
    $result = [
        'success' => true,
        'message' => 'Password rehash complete',
        'data' => [
            'total_users' => $total,
            'rehashed' => $rehashed,
            'already_hashed' => $skipped
        ]
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
    echo "\n\n⚠️  DELETE THIS FILE NOW: rehash_passwords.php\n";
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Rehash failed: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
