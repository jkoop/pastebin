<?php

function crockford32_normalize(string $string): string|null {
    $string = strtoupper($string);
    $string = str_replace(
        search: ["O", "I", "L", "U"], // this U replacement is non-standard
        replace: ["0", "1", "1", "V"],
        subject: $string,
    );
    if (preg_match("/[^0-9A-HJKMNP-TV-Z]/", $string)) {
        return null; // input string contained invalid chars
    }
    return $string;
}

function dd(string $message): never {
    http_response_code(500);
    header("Content-Type: text/plain");

    if ($method != "HEAD" && $method != "OPTIONS") {
        echo $message . "\n";
    }

    exit;
}

define("EXPIRES_SECONDS", (int) ($_ENV["EXPIRES_SECONDS"] ?? (60 * 60 * 24 * 7))); // 1 week
define("MAX_PATH_LENGTH", (int) ($_ENV["MAX_PATH_LENGTH"] ?? 8));
define("ICON_URL", $_ENV["ICON_URL"] ?? "https://www.w3.org/Icons/burst.png");
define("DOMAIN_NAME", $_ENV["DOMAIN_NAME"] ?? "example.com");
define("PASSWORD", $_ENV["PASSWORD"] ?? null);

if (EXPIRES_SECONDS < 60) dd("EXPIRES_SECONDS is too low");
if (MAX_PATH_LENGTH < 4) dd("MAX_PATH_LENGTH is too low");
if (MAX_PATH_LENGTH > 220) dd("MAX_PATH_LENGTH is too high");

$method = $_SERVER["REQUEST_METHOD"];
$path = str_replace("/", "", $_SERVER["REQUEST_URI"]);

@chdir("/data");
if (getcwd() != "/data") dd("FATAL: Directory /data is missing. You probably forgot to bind a mount for it.");

// delete old files
exec("find -mmin +" . ceil(EXPIRES_SECONDS / 60) . " -delete");

if (preg_match("/^favicon\.[A-Za-z0-9]{1,5}/", $path)) {
    header("Location: " . ICON_URL);
    exit;
} else if ($path != "") {
    if ($method == "OPTIONS") {
        header("Allow: OPTIONS, GET, HEAD");
        header("Cache-Control: public, max-age=31536000, immutable");
        exit;
    } else if ($method == "HEAD" || $method == "GET") {
        if (strlen($path) > 220) {
            goto not_found;
        }

        $requested_path = $path;
        $path = crockford32_normalize($path);

        if ($path === null) {
            goto not_found;
        }

        $path = strtolower($path);
        $content_time = @filemtime($path . ".content");
        $content_length = @filesize($path . ".content");
        $content_fp = @fopen($path . ".content", "r");
        $type_fp = @fopen($path . ".type", "r");
        $expires = $content_time + EXPIRES_SECONDS - time();

        if (
            $content_time === false ||
            $content_length === false ||
            $content_fp === false ||
            $type_fp === false ||
            ($content_time !== false && $expires < 1)
        ) {
            not_found:
            http_response_code(404);
            header("Content-Type: text/html; charset=utf-8");
            header("Content-Length: " . filesize("/404.html"));

            if ($method != "OPTIONS" && $method != "HEAD") {
                readfile("/404.html");
            }

            exit;
        }

        if ($path != $requested_path) {
            header("Location: /" . $path);
            header("Content-Type: text/plain");

            if ($method == "GET") {
                echo "Go to /" . $path . "\n";
            }

            exit;
        }

        header("Content-Type: " . stream_get_contents($type_fp));
        header("Content-Length: " . $content_length);
        header("Cache-Control: public, max-age=" . $expires . ", immutable");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s \G\M\T", $content_time));

        if ($method == "GET") {
            fpassthru($content_fp);
        }

        exit;
    } else {
        http_response_code(405);
        header("Allow: OPTIONS, GET, HEAD");
        header("Cache-Control: public, max-age=31536000, immutable");
        exit;
    }
} else {
    if (PASSWORD !== null) {
        if (($_SERVER["PHP_AUTH_PW"] ?? null) !== PASSWORD) {
            http_response_code(401);
            header("Content-Type: text/html; charset=utf-8");
            header("Content-Length: " . filesize("/401.html"));
            header('WWW-Authenticate: Basic realm="Protected", charset="UTF-8"');

            if ($method != "OPTIONS" && $method != "HEAD") {
                readfile("/401.html");
            }

            exit;
        }
    }

    if ($method == "OPTIONS") {
        header("Allow: OPTIONS, GET, HEAD, POST");
        header("Cache-Control: public, max-age=31536000, immutable");
        exit;
    } else if ($method == "HEAD" || $method == "GET") {
        $expire = number_format(EXPIRES_SECONDS);
        $extra_html = <<<HTML
        <script>
        p = document.createElement("p");
        p.innerText = "Pastes live for $expire seconds.";
        document.querySelector("h1").after(p);
        </script>
        HTML;

        header("Content-Type: text/html; charset=utf-8");
        header("Content-Length: " . filesize("/index.html") + strlen($extra_html));
        header("Cache-Control: private");

        if ($method == "GET") {
            readfile("/index.html");
            echo $extra_html;
        }

        exit;
    } else if ($method == "POST") {
        $name = exec("head -c 140 /dev/urandom | base32 -w 0");
        $name = crockford32_normalize($name);
        $name = strtolower($name);
        $name = substr($name, 0, MAX_PATH_LENGTH);
        $type_name = $name . ".type";
        $content_name = $name . ".content";

        $text = $_POST["text"] ?? "";
        $text = trim($text);

        $file = $_FILES["file"] ?? null;
        if ($file !== null && $file["error"] != UPLOAD_ERR_OK) $file = null;

        if ($text != "") {
            file_put_contents($content_name, $text);
            file_put_contents($type_name, "text/plain");
        } else if ($file !== null) {
            move_uploaded_file($file["tmp_name"], $content_name);
            file_put_contents($type_name, substr(preg_replace("/\s/", " ", $file["type"]), 0, 100));
        } else {
            header("Location: /");
            exit;
        }

        header("Content-Type: text/plain");
        echo "https://" . DOMAIN_NAME . "/" . $name . "\n";
        exit;
    } else {
        http_response_code(405);
        header("Allow: OPTIONS, GET, HEAD, POST");
        header("Cache-Control: public, max-age=31536000, immutable");
        exit;
    }
}
