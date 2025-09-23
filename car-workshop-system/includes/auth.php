<?php
/*  Authentication Middleware. */

require_once __DIR__ . '/../src/config/AppConfig.php';
require_once __DIR__ . '/../src/config/database.php';

class AuthMiddleware {
	// Session and security settings
	// Session of an hour. 
	// You can attempt login 5 times before being locked out for 15 minutes.
	private const SESSION_LIFETIME = 3600; // 1 hour
	private const MAX_LOGIN_ATTEMPTS = 5;
	private const LOCKOUT_TIME = 900; // 15 minutes

	public function __construct() {
		$this->secureSessionStart();
	}

	/* Start a secure session and enforce timeout */
	private function secureSessionStart(): void {
		if (session_status() === PHP_SESSION_NONE) {
			// Harden session cookie handling and set sensible defaults for the app.
			
			// - session.cookie_httponly: when enabled, JavaScript running in the browser
			//   cannot read the session cookie. This mitigates XSS-based cookie theft.
			// - session.cookie_secure: when enabled the session cookie is only sent
			//   over HTTPS. It should be set to 1 when HTTPS is used.
			// - session.cookie_samesite: provides cross-site request control. 'Lax'
			//   is default that still allows top-level navigations while
			//   reducing CSRF risks for some cross-site requests.
			// - session.use_strict_mode: prevents uninitialized session IDs from being
			//   accepted by the application (helps with session fixation attacks).
			ini_set('session.cookie_httponly', 1);
			ini_set('session.cookie_secure', 0); 
			ini_set('session.cookie_samesite', 'Lax');
			ini_set('session.use_strict_mode', 1);

			session_name('CAR_WORKSHOP_SESSION');

			// session_set_cookie_params controls the cookie attributes sent to the
			// browser when the session cookie is created. The browser will receive
			// a cookie like: Set-Cookie: CAR_WORKSHOP_SESSION=<session_id>; Path=/; HttpOnly; SameSite=Lax
			session_set_cookie_params([
				'lifetime' => self::SESSION_LIFETIME, // how long the cookie is valid (seconds)
				'path' => '/',                          // cookie visible site-wide
				'domain' => '',                         // default: current host
				'secure' => false,                     
				'httponly' => true,
				'samesite' => 'Lax'
			]);

			// Start the session — this sends the Set-Cookie header to the browser
			// (if the session id is new) and loads any existing session data into
			// the $_SESSION superglobal. The cookie received by the browser will
			// contain only the session id; the application associates that id
			// with server-side data in the session store 
			session_start();

			// Regenerate on new session to prevent fixation
			if (!isset($_SESSION['created'])) {
				session_regenerate_id(true);
				$_SESSION['created'] = time();
			}
		}

		// Enforce idle timeout and update activity
		if (isset($_SESSION['last_activity'])) {
			if ((time() - $_SESSION['last_activity']) > self::SESSION_LIFETIME) {
				$this->destroySession();
				return;
			}
		}
		$_SESSION['last_activity'] = time();
	}

	/**
	 *
	 * 
	 * - Store a lightweight "fingerprint" in the session to make simple
	 *   hijack detection easier (it ties the session to the user's User-Agent
	 *   and user id). 
	 * - Session lifecycle: on successful login we regenerate the session id
	 *   (to prevent fixation), set $_SESSION['created'] and $_SESSION['last_activity'],
	 *   and create a fingerprint. On each request the middleware checks last_activity
	 *   to implement idle timeout and validates the fingerprint to detect tampering.
	 */

	
	/* Require login - redirect if not authenticated */
	// This function is called on top of every dashboard page
	public function requireLogin($redirectTo = '/Apallaktiki/car-workshop-system/public/login.php') {
		if (!$this->isLoggedIn()) {
			// Check if this is an AJAX request
			if ($this->isAjaxRequest()) {

				// Clear any output buffer (html, whitespace etc) to ensure clean JSON
				while (ob_get_level()) {
					ob_end_clean();
				}
				
				// Return JSON error instead of redirect
				if (!headers_sent()) {
					header('Content-Type: application/json; charset=utf-8');
					http_response_code(401);
				}
				
				$response = [
					'success' => false,
					'message' => 'Authentication required. Please log in.',
					'redirect' => $redirectTo,
					'error_code' => 'AUTH_REQUIRED'
				];
				
				echo json_encode($response, JSON_UNESCAPED_UNICODE);
				exit;
			}
			
			// Traditional redirect for regular page requests
			$_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
			header("Location: $redirectTo");
			exit;
		}
	}

	// Require specific role
	public function requireRole($role, $redirectTo = 'unauthorized.php') {
		$this->requireLogin();
		if (!$this->hasRole($role)) {
			header("Location: $redirectTo");
			exit;
		}
	}

	// Redirect if already logged in
	public function redirectIfLoggedIn($redirectTo = 'dashboard.php') {
		if ($this->isLoggedIn()) {
			header("Location: $redirectTo");
			exit;
		}
	}

	// Check if user is logged in and session is valid
	public function isLoggedIn(): bool {
		return isset($_SESSION['user_id']) && $this->validateSession();
	}

	// Check if the current request is an AJAX request
	// heuristics to detect AJAX/API calls so the middleware can return JSON instead of HTML redirect.
	// For normal browser page requests: perform an HTTP redirect to the login page
	// so the user sees the login screen and the browser updates the URL.
	// For AJAX/fetch requests: return a clean JSON error and a 401
	// so the client-side code can handle it (show modal, redirect client-side, display message).
	private function isAjaxRequest(): bool {
		// Check for XMLHttpRequest header
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
			strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			return true;
		}
		
		// Check if Accept header indicates JSON is preferred
		if (!empty($_SERVER['HTTP_ACCEPT']) && 
			strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
			return true;
		}
		
		// Check if Content-Type is JSON (for POST/PUT requests)
		if (!empty($_SERVER['CONTENT_TYPE']) && 
			strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
			return true;
		}
		
		return false;
	}

	// Validate session integrity and timeout
	private function validateSession(): bool {
		if (!isset($_SESSION['created'])) {
			$_SESSION['created'] = time();
		}
		if (!isset($_SESSION['last_activity'])) {
			$_SESSION['last_activity'] = time();
			return true;
		}
		if ((time() - $_SESSION['last_activity']) > self::SESSION_LIFETIME) {
			$this->destroySession();
			return false;
		}

		// Lightweight hijacking check: validate fingerprint if present
		if (isset($_SESSION['user_id']) && isset($_SESSION['fingerprint'])) {
			$expected = $this->computeFingerprint((int)$_SESSION['user_id']);
			if (!hash_equals($_SESSION['fingerprint'], $expected)) {
				$this->destroySession();
				return false;
			}
		}
		$_SESSION['last_activity'] = time();
		return true;
	}

	// Login user with lockout and session fixation protection
	public function loginUser($email, $password): array {
		$result = ['success' => false, 'message' => '', 'redirect' => ''];

		try {
			if ($this->isLockedOut($email)) {
				$result['message'] = 'Account temporarily locked due to multiple failed attempts.';
				return $result;
			}

			$db = Database::getInstance()->getConnection();
			$stmt = $db->prepare("SELECT id, username, email, password, role, status, first_name, last_name FROM users WHERE email = ? AND deleted_at IS NULL");
			$stmt->execute([$email]);
			$user = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$user || !password_verify($password, $user['password'])) {
				$this->recordFailedLogin($email);
				$result['message'] = 'Invalid email or password.';
				return $result;
			}

			if ($user['status'] !== 'active') {
				$result['message'] = 'Account is suspended. Contact support.';
				return $result;
			}

			$this->createUserSession($user);
			$this->clearFailedLogins($email);

			$result['success'] = true;
			$result['message'] = 'Login successful';
			$result['redirect'] = $this->getRedirectUrl($user['role']);
			return $result;
		} catch (Exception $e) {
			error_log('Login error: ' . $e->getMessage());
			$result['message'] = 'An error occurred during login. Please try again.';
			return $result;
		}
	}

	// Create session and regenerate ID to avoid fixation
	private function createUserSession(array $user): void {
		// Regenerate on successful login
		session_regenerate_id(true);
		$_SESSION['user_id'] = (int)$user['id'];
		$_SESSION['email'] = $user['email'];
		$_SESSION['username'] = $user['username'];
		$_SESSION['role'] = $user['role'];
		$_SESSION['last_activity'] = time();
		$_SESSION['created'] = time();
		// Lightweight fingerprint (experimental)
		$_SESSION['fingerprint'] = $this->computeFingerprint((int)$user['id']);
	}

	private function computeFingerprint(int $userId): string {
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
		// Note: IP can change on some networks; rely primarily on User-Agent
		return hash('sha256', $userAgent . '|' . $userId);
	}

	// Logout and fully destroy the session
	public function logout(): void {
		$this->destroySession();
	}

	private function destroySession(): void {
		$_SESSION = [];
		if (isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time() - 3600, '/');
		}
		
		session_destroy();
	}

	// Role checks using AppConfig hierarchy
	public function hasRole(string $requiredRole): bool {
		$userRole = $_SESSION['role'] ?? 'visitor';
		return AppConfig::hasPermission($userRole, $requiredRole);
	}

	// Get current user, with basic info, and lazy-load sensitive data
	public function getCurrentUser(): ?array {
		if (!$this->isLoggedIn()) {
			return null;
		}
		$basic = [
			'id' => $_SESSION['user_id'],
			'email' => $_SESSION['email'] ?? '',
			'username' => $_SESSION['username'] ?? '',
			'role' => $_SESSION['role'] ?? 'visitor',
		];
		try {
			$db = Database::getInstance()->getConnection();
			$stmt = $db->prepare('SELECT first_name, last_name, id_number FROM users WHERE id = ? AND deleted_at IS NULL');
			$stmt->execute([$_SESSION['user_id']]);
			$extra = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
			return array_merge($basic, $extra);
		} catch (Exception $e) {
			return $basic + ['first_name' => '', 'last_name' => '', 'id_number' => ''];
		}
	}

	// Brute force protection (per-session simple lockout)
	private function isLockedOut(string $email): bool {
		$attempts = $_SESSION['login_attempts'][$email] ?? 0;
		$lastAttempt = $_SESSION['last_attempt'][$email] ?? 0;
		if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
			if (time() - $lastAttempt < self::LOCKOUT_TIME) {
				return true;
			}
			unset($_SESSION['login_attempts'][$email], $_SESSION['last_attempt'][$email]);
		}
		return false;
	}

	private function recordFailedLogin(string $email): void {
		$_SESSION['login_attempts'][$email] = ($_SESSION['login_attempts'][$email] ?? 0) + 1;
		$_SESSION['last_attempt'][$email] = time();
	}

	private function clearFailedLogins(string $email): void {
		unset($_SESSION['login_attempts'][$email], $_SESSION['last_attempt'][$email]);
	}

	private function getRedirectUrl(string $role): string {
		$redirects = [
			'visitor' => 'index.php',
			'customer' => 'dashboard.php',
			'mechanic' => 'dashboard.php',
			'secretary' => 'dashboard.php',
		];
		return $redirects[$role] ?? 'dashboard.php';
	}
}

// Global instance
$auth = new AuthMiddleware();
