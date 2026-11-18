
<?php


require_once __DIR__ . '/config/bootstrap.php';

$user = current_user();

if (!$user) {
    json_response([
        'ok' => false,
        'error' => 'Your session has expired. Please log in again.'
    ], 401);
}

$uid = (int) $user['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/*
|--------------------------------------------------------------------------
| DATABASE COLUMN HELPERS
|--------------------------------------------------------------------------
*/

function get_message_columns(): array
{
    $stmt = db()->query("PRAGMA table_info(messages)");

    return array_column(
        $stmt->fetchAll(PDO::FETCH_ASSOC),
        'name'
    );
}

function message_column_exists(string $column): bool
{
    return in_array($column, get_message_columns(), true);
}

function message_text_column(): string
{
    $columns = get_message_columns();

    if (in_array('message', $columns, true)) {
        return 'message';
    }

    if (in_array('body', $columns, true)) {
        return 'body';
    }

    throw new RuntimeException(
        'Messages table must contain a message or body column.'
    );
}

/*
|--------------------------------------------------------------------------
| ATTACHMENT COLUMN INITIALIZATION
|--------------------------------------------------------------------------
*/

function ensure_message_attachment_columns(): void
{
    $columns = get_message_columns();

    $attachments = [
        'attachment_path' => 'TEXT',
        'attachment_name' => 'TEXT',
        'attachment_mime' => 'TEXT'
    ];

    foreach ($attachments as $column => $type) {
        if (!in_array($column, $columns, true)) {
            db()->exec(
                "ALTER TABLE messages ADD COLUMN {$column} {$type}"
            );

            $columns[] = $column;
        }
    }
}

/*
|--------------------------------------------------------------------------
| MESSAGE INSERT HELPER
|--------------------------------------------------------------------------
*/

function insert_chat_message(array $data): int
{
    $existingColumns = get_message_columns();

    $columns = [];
    $values = [];

    foreach ($data as $column => $value) {
        if (in_array($column, $existingColumns, true)) {
            $columns[] = $column;
            $values[] = $value;
        }
    }

    $textColumn = message_text_column();

    if (!in_array($textColumn, $columns, true)) {
        throw new RuntimeException(
            'Message content is required.'
        );
    }

    // Populate both columns if the database has both.
    // This maintains compatibility with older database schemas.
    if (
        in_array('message', $existingColumns, true) &&
        !in_array('message', $columns, true)
    ) {
        $columns[] = 'message';
        $values[] = '';
    }

    if (
        in_array('body', $existingColumns, true) &&
        !in_array('body', $columns, true)
    ) {
        $columns[] = 'body';
        $values[] = '';
    }

    $placeholders = implode(
        ', ',
        array_fill(0, count($columns), '?')
    );

    $sql = "
        INSERT INTO messages (" .
        implode(', ', $columns) .
        ") VALUES ({$placeholders})
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($values);

    return (int) db()->lastInsertId();
}

/*
|--------------------------------------------------------------------------
| REQUEST HELPERS
|--------------------------------------------------------------------------
*/

function input_json(): array
{
    $raw = file_get_contents('php://input');

    $data = json_decode($raw ?: '{}', true);

    return is_array($data) ? $data : [];
}

function require_conversation(int $cid, int $uid): void
{
    if ($cid < 1 || !is_member($cid, $uid)) {
        json_response([
            'ok' => false,
            'error' => 'Conversation not found.'
        ], 404);
    }
}

/*
|--------------------------------------------------------------------------
| CONVERSATION SUMMARY
|--------------------------------------------------------------------------
*/

function conversation_summary(int $cid, int $uid): ?array
{
    $textColumn = message_text_column();

    $stmt = db()->prepare("
        SELECT
            c.id,
            c.type,
            c.name,

            (
                SELECT m.{$textColumn}
                FROM messages m
                WHERE m.conversation_id = c.id
                ORDER BY m.id DESC
                LIMIT 1
            ) AS last_message,

            (
                SELECT m.created_at
                FROM messages m
                WHERE m.conversation_id = c.id
                ORDER BY m.id DESC
                LIMIT 1
            ) AS last_time,

            c.created_at

        FROM conversations c

        JOIN conversation_members mine
            ON mine.conversation_id = c.id
            AND mine.user_id = ?

        WHERE c.id = ?
    ");

    $stmt->execute([$uid, $cid]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    if ($row['type'] === 'private') {

        $stmt = db()->prepare("
            SELECT
                u.id,
                u.fullname,
                u.username,
                u.status,
                u.avatar

            FROM users u

            JOIN conversation_members cm
                ON cm.user_id = u.id

            WHERE cm.conversation_id = ?
              AND u.id <> ?

            LIMIT 1
        ");

        $stmt->execute([$cid, $uid]);

        $other = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $row['title'] = $other['fullname'] ?? 'Private chat';

        $row['subtitle'] = isset($other['username'])
            ? '@' . $other['username']
            : '';

        $row['avatar'] = $other['avatar'] ?? '';

        $row['online'] = ($other['status'] ?? '') === 'online';

        $row['other_user_id'] = isset($other['id'])
            ? (int) $other['id']
            : null;

    } else {

        $row['title'] = $row['name'] ?: 'Group conversation';

        $row['subtitle'] = 'Group conversation';

        $row['avatar'] = '';

        $row['online'] = false;

        $row['other_user_id'] = null;
    }

    // Unread message count.

    $stmt = db()->prepare("
        SELECT COUNT(*)

        FROM messages m

        WHERE m.conversation_id = ?
          AND m.sender_id <> ?

          AND NOT EXISTS (
              SELECT 1

              FROM message_reads r

              WHERE r.message_id = m.id
                AND r.user_id = ?
          )
    ");

    $stmt->execute([$cid, $uid, $uid]);

    $row['unread'] = (int) $stmt->fetchColumn();

    return $row;
}

/*
|--------------------------------------------------------------------------
| INITIALIZE ATTACHMENTS
|--------------------------------------------------------------------------
*/

ensure_message_attachment_columns();

/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

/*
|--------------------------------------------------------------------------
| API ACTIONS
|--------------------------------------------------------------------------
*/

switch ($action) {

    /*
    |--------------------------------------------------------------------------
    | CONVERSATIONS
    |--------------------------------------------------------------------------
    */

    case 'conversations':

        $stmt = db()->prepare("
            SELECT conversation_id

            FROM conversation_members

            WHERE user_id = ?
        ");

        $stmt->execute([$uid]);

        $items = [];

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cid) {

            $item = conversation_summary((int) $cid, $uid);

            if ($item) {
                $items[] = $item;
            }
        }

        usort(
            $items,
            fn($a, $b) => strcmp(
                $b['last_time'] ?? $b['created_at'] ?? '',
                $a['last_time'] ?? $a['created_at'] ?? ''
            )
        );

        json_response([
            'ok' => true,
            'items' => $items
        ]);

    /*
    |--------------------------------------------------------------------------
    | PEOPLE
    |--------------------------------------------------------------------------
    */

    case 'people':

        $q = trim((string) ($_GET['q'] ?? ''));

        $stmt = db()->prepare("
            SELECT
                id,
                fullname,
                username,
                status,
                avatar,
                bio

            FROM users

            WHERE id <> ?
              AND (
                  fullname LIKE ?
                  OR username LIKE ?
              )

            ORDER BY status = 'online' DESC, fullname

            LIMIT 80
        ");

        $like = '%' . $q . '%';

        $stmt->execute([$uid, $like, $like]);

        json_response([
            'ok' => true,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    /*
    |--------------------------------------------------------------------------
    | START PRIVATE CONVERSATION
    |--------------------------------------------------------------------------
    */

    case 'start_private':

        $data = input_json();

        $other = (int) ($data['user_id'] ?? 0);

        if ($other === $uid || $other < 1) {
            json_response([
                'ok' => false,
                'error' => 'Choose a valid person.'
            ], 422);
        }

        $stmt = db()->prepare("
            SELECT id FROM users WHERE id = ?
        ");

        $stmt->execute([$other]);

        if (!$stmt->fetchColumn()) {
            json_response([
                'ok' => false,
                'error' => 'User not found.'
            ], 404);
        }

        $stmt = db()->prepare("
            SELECT c.id

            FROM conversations c

            JOIN conversation_members a
                ON a.conversation_id = c.id
                AND a.user_id = ?

            JOIN conversation_members b
                ON b.conversation_id = c.id
                AND b.user_id = ?

            WHERE c.type = 'private'

            LIMIT 1
        ");

        $stmt->execute([$uid, $other]);

        $cid = (int) ($stmt->fetchColumn() ?: 0);

        if (!$cid) {

            db()->beginTransaction();

            try {

                db()->prepare("
                    INSERT INTO conversations (type, created_by)

                    VALUES ('private', ?)
                ")->execute([$uid]);

                $cid = (int) db()->lastInsertId();

                $member = db()->prepare("
                    INSERT INTO conversation_members (
                        conversation_id,
                        user_id
                    )

                    VALUES (?, ?)
                ");

                $member->execute([$cid, $uid]);

                $member->execute([$cid, $other]);

                db()->commit();

            } catch (Throwable $e) {

                if (db()->inTransaction()) {
                    db()->rollBack();
                }

                throw $e;
            }
        }

        json_response([
            'ok' => true,
            'conversation' => conversation_summary($cid, $uid)
        ]);

    /*
    |--------------------------------------------------------------------------
    | CREATE GROUP
    |--------------------------------------------------------------------------
    */

    case 'create_group':

        $data = input_json();

        $name = trim((string) ($data['name'] ?? ''));

        $rawMembers = $data['members'] ?? [];

        if (!is_array($rawMembers)) {
            json_response([
                'ok' => false,
                'error' => 'Invalid group members.'
            ], 422);
        }

        $members = array_values(array_unique(array_filter(
            array_map('intval', $rawMembers),
            fn($id) => $id > 0 && $id !== $uid
        )));

        if (
            $name === '' ||
            mb_strlen($name) > 80 ||
            count($members) < 1
        ) {
            json_response([
                'ok' => false,
                'error' => 'Enter a group name and select at least one other member.'
            ], 422);
        }

        $marks = implode(',', array_fill(0, count($members), '?'));

        $stmt = db()->prepare("
            SELECT id

            FROM users

            WHERE id IN ($marks)
        ");

        $stmt->execute($members);

        $validMembers = array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );

        if (count($validMembers) !== count($members)) {
            json_response([
                'ok' => false,
                'error' => 'One or more selected members do not exist.'
            ], 422);
        }

        db()->beginTransaction();

        try {

            db()->prepare("
                INSERT INTO conversations (
                    type,
                    name,
                    created_by
                )

                VALUES ('group', ?, ?)
            ")->execute([$name, $uid]);

            $cid = (int) db()->lastInsertId();

            $member = db()->prepare("
                INSERT INTO conversation_members (
                    conversation_id,
                    user_id
                )

                VALUES (?, ?)
            ");

            $member->execute([$cid, $uid]);

            foreach ($members as $memberId) {
                $member->execute([$cid, $memberId]);
            }

            db()->commit();

        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }

            throw $e;
        }

        json_response([
            'ok' => true,
            'conversation' => conversation_summary($cid, $uid)
        ]);

    /*
    |--------------------------------------------------------------------------
    | GET MESSAGES
    |--------------------------------------------------------------------------
    */

    case 'messages':

        $cid = (int) ($_GET['conversation_id'] ?? 0);

        require_conversation($cid, $uid);

        $after = max(0, (int) ($_GET['after'] ?? 0));

        $textColumn = message_text_column();

        $columns = get_message_columns();

        $attachmentPath = in_array('attachment_path', $columns, true)
            ? 'm.attachment_path'
            : 'NULL';

        $attachmentName = in_array('attachment_name', $columns, true)
            ? 'm.attachment_name'
            : 'NULL';

        $attachmentMime = in_array('attachment_mime', $columns, true)
            ? 'm.attachment_mime'
            : 'NULL';

        $editedAt = in_array('edited_at', $columns, true)
            ? 'm.edited_at'
            : 'NULL';

        $stmt = db()->prepare("
            SELECT
                m.id,
                m.conversation_id,
                m.sender_id,

                m.{$textColumn} AS body,

                {$attachmentPath} AS attachment_path,
                {$attachmentName} AS attachment_name,
                {$attachmentMime} AS attachment_mime,

                m.created_at,

                {$editedAt} AS edited_at,

                u.fullname,
                u.username,
                u.avatar

            FROM messages m

            JOIN users u
                ON u.id = m.sender_id

            WHERE m.conversation_id = ?
              AND m.id > ?

            ORDER BY m.id ASC

            LIMIT 100
        ");

        $stmt->execute([$cid, $after]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark received messages as read.

        if ($items) {

            $ids = array_column($items, 'id');

            $marks = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            db()->prepare("
                INSERT OR IGNORE INTO message_reads (
                    message_id,
                    user_id
                )

                SELECT id, ?

                FROM messages

                WHERE id IN ($marks)
                  AND sender_id <> ?
            ")->execute(
                array_merge([$uid], $ids, [$uid])
            );
        }

        json_response([
            'ok' => true,
            'items' => $items
        ]);

    /*
    |--------------------------------------------------------------------------
    | SEND MESSAGE
    |--------------------------------------------------------------------------
    */

    case 'send':

        $data = input_json();

        $cid = (int) ($data['conversation_id'] ?? 0);

        require_conversation($cid, $uid);

        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '' || mb_strlen($body) > 5000) {
            json_response([
                'ok' => false,
                'error' => 'Message must contain 1–5000 characters.'
            ], 422);
        }

        $messageId = insert_chat_message([
            'conversation_id' => $cid,
            'sender_id' => $uid,
            'message' => $body,
            'body' => $body,
            'message_type' => 'text'
        ]);

        json_response([
            'ok' => true,
            'id' => $messageId
        ]);

    /*
    |--------------------------------------------------------------------------
    | FILE UPLOAD
    |--------------------------------------------------------------------------
    */

    case 'upload':

        $cid = (int) ($_POST['conversation_id'] ?? 0);

        require_conversation($cid, $uid);

        if (
            empty($_FILES['file']) ||
            $_FILES['file']['error'] !== UPLOAD_ERR_OK
        ) {
            json_response([
                'ok' => false,
                'error' => 'Upload failed.'
            ], 422);
        }

        $file = $_FILES['file'];

        if ($file['size'] > 10 * 1024 * 1024) {
            json_response([
                'ok' => false,
                'error' => 'Maximum file size is 10 MB.'
            ], 422);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))
            ->file($file['tmp_name'])
            ?: 'application/octet-stream';

        $allowed = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        if (!in_array($mime, $allowed, true)) {
            json_response([
                'ok' => false,
                'error' => 'File type is not allowed.'
            ], 422);
        }

        $dir = __DIR__ . '/uploads';

        if (
            !is_dir($dir) &&
            !mkdir($dir, 0775, true) &&
            !is_dir($dir)
        ) {
            json_response([
                'ok' => false,
                'error' => 'Could not create upload directory.'
            ], 500);
        }

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $storedName = bin2hex(random_bytes(16));

        if ($extension !== '') {
            $storedName .= '.' . $extension;
        }

        $destination = $dir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            json_response([
                'ok' => false,
                'error' => 'Could not save uploaded file.'
            ], 500);
        }

        $path = 'uploads/' . $storedName;

        try {

            $messageId = insert_chat_message([
                'conversation_id' => $cid,
                'sender_id' => $uid,
                'message' => '',
                'body' => '',
                'message_type' => 'file',
                'attachment_path' => $path,
                'attachment_name' => basename($file['name']),
                'attachment_mime' => $mime
            ]);

        } catch (Throwable $e) {

            @unlink($destination);

            throw $e;
        }

        json_response([
            'ok' => true,
            'id' => $messageId
        ]);

    /*
    |--------------------------------------------------------------------------
    | SEND CALL SIGNAL
    |--------------------------------------------------------------------------
    */

    case 'signal_send':

        $data = input_json();

        $cid = (int) ($data['conversation_id'] ?? 0);

        $to = (int) ($data['recipient_id'] ?? 0);

        $type = (string) ($data['signal_type'] ?? '');

        $payload = $data['payload'] ?? null;

        require_conversation($cid, $uid);

        if (
            !$to ||
            $to === $uid ||
            !is_member($cid, $to) ||
            !in_array($type, ['offer', 'answer', 'ice', 'hangup'], true) ||
            !is_array($payload)
        ) {
            json_response([
                'ok' => false,
                'error' => 'Invalid call signal.'
            ], 422);
        }

        $stmt = db()->prepare("
            INSERT INTO call_signals (
                conversation_id,
                sender_id,
                recipient_id,
                signal_type,
                payload
            )

            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $cid,
            $uid,
            $to,
            $type,
            json_encode($payload, JSON_THROW_ON_ERROR)
        ]);

        json_response([
            'ok' => true
        ]);

    /*
    |--------------------------------------------------------------------------
    | RECEIVE CALL SIGNAL
    |--------------------------------------------------------------------------
    */

    case 'signal_poll':

        $cid = (int) ($_GET['conversation_id'] ?? 0);

        require_conversation($cid, $uid);

        $stmt = db()->prepare("
            SELECT
                id,
                sender_id,
                signal_type,
                payload

            FROM call_signals

            WHERE conversation_id = ?
              AND recipient_id = ?
              AND consumed = 0

            ORDER BY id

            LIMIT 100
        ");

        $stmt->execute([$cid, $uid]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($items) {

            $ids = array_column($items, 'id');

            $marks = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            db()->prepare("
                UPDATE call_signals

                SET consumed = 1

                WHERE id IN ($marks)
                  AND recipient_id = ?
            ")->execute(
                array_merge($ids, [$uid])
            );
        }

        foreach ($items as &$item) {
            $item['payload'] = json_decode(
                $item['payload'],
                true
            );
        }

        unset($item);

        json_response([
            'ok' => true,
            'items' => $items
        ]);

    /*
    |--------------------------------------------------------------------------
    | TRANSLATE MESSAGE
    |--------------------------------------------------------------------------
    */

    case 'translate':

        $data = input_json();

        $text = trim((string) ($data['text'] ?? ''));

        $target = strtolower(
            trim((string) ($data['target'] ?? 'en'))
        );

        $allowedLanguages = [
            'en', 'tl', 'es', 'fr', 'de', 'it', 'pt',
            'ja', 'ko', 'zh', 'ar', 'hi', 'ru', 'nl',
            'pl', 'tr', 'vi', 'th', 'id'
        ];

        if ($text === '' || mb_strlen($text) > 5000) {
            json_response([
                'ok' => false,
                'error' => 'Enter a message under 5,000 characters.'
            ], 422);
        }

        if (!in_array($target, $allowedLanguages, true)) {
            json_response([
                'ok' => false,
                'error' => 'Unsupported target language.'
            ], 422);
        }

        if (!function_exists('curl_init')) {
            json_response([
                'ok' => false,
                'error' => 'PHP cURL is required for translation. Enable the cURL extension.'
            ], 500);
        }

        $translateUrl = getenv('CHATCONNECT_TRANSLATE_URL')
    ?: 'http://127.0.0.1:5001/translate';

$apiKey = '';

        $payload = [
            'q' => $text,
            'source' => 'auto',
            'target' => $target,
            'format' => 'text'
        ];

        if ($apiKey !== '') {
            $payload['api_key'] = $apiKey;
        }

        $ch = curl_init($translateUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            ),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25
        ]);

        $response = curl_exec($ch);

        $curlError = curl_error($ch);

        $httpCode = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        if (
            $response === false ||
            $httpCode < 200 ||
            $httpCode >= 300
        ) {
            error_log(
                'ChatConnect translation error: ' .
                $curlError .
                ' HTTP ' .
                $httpCode
            );

            json_response([
                'ok' => false,
                'error' => 'Translation service is unavailable. Check the translation provider URL or API key.'
            ], 502);
        }

        $result = json_decode($response, true);

        $translatedText = is_array($result)
            ? ($result['translatedText'] ?? '')
            : '';

        if (
            !is_string($translatedText) ||
            $translatedText === ''
        ) {
            json_response([
                'ok' => false,
                'error' => 'The translation provider returned an invalid response.'
            ], 502);
        }

        $detectedLanguage = null;

        if (isset($result['detectedLanguage']['language'])) {
            $detectedLanguage =
                $result['detectedLanguage']['language'];

        } elseif (
            isset($result['detectedLanguage']) &&
            is_string($result['detectedLanguage'])
        ) {
            $detectedLanguage = $result['detectedLanguage'];
        }

        json_response([
            'ok' => true,
            'translatedText' => $translatedText,
            'detectedLanguage' => $detectedLanguage
        ]);

    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE
    |--------------------------------------------------------------------------
    */

    case 'profile':

        $data = input_json();

        $name = trim((string) ($data['fullname'] ?? ''));

        $bio = trim((string) ($data['bio'] ?? ''));

        if (
            $name === '' ||
            mb_strlen($name) > 100 ||
            mb_strlen($bio) > 180
        ) {
            json_response([
                'ok' => false,
                'error' => 'Check your name and bio.'
            ], 422);
        }

        db()->prepare("
            UPDATE users

            SET fullname = ?, bio = ?

            WHERE id = ?
        ")->execute([
            $name,
            $bio,
            $uid
        ]);

        json_response([
            'ok' => true
        ]);

    /*
    |--------------------------------------------------------------------------
    | ADMIN USERS
    |--------------------------------------------------------------------------
    */

    case 'admin_users':

        if (($user['role'] ?? '') !== 'admin') {
            json_response([
                'ok' => false,
                'error' => 'Administrator access required.'
            ], 403);
        }

        $stmt = db()->query("
            SELECT
                id,
                fullname,
                username,
                email,
                role,
                status,
                created_at

            FROM users

            ORDER BY id DESC

            LIMIT 200
        ");

        json_response([
            'ok' => true,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);

    /*
    |--------------------------------------------------------------------------
    | UNKNOWN ACTION
    |--------------------------------------------------------------------------
    */

    default:

        json_response([
            'ok' => false,
            'error' => 'Unknown action.'
        ], 404);
}
