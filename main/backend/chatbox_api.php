<?php
declare(strict_types=1);



class ChatBoxAPI {
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $baseUrl = 'http://localhost:8000', int $timeout = 300) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }


    public function healthCheck(): bool {
        try {
            $ch = curl_init($this->baseUrl . '/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (Exception $e) {
            return false;
        }
    }


    public function uploadVideo(string $videoPath, string $skill = 'drive_forehand'): ?array {
        if (!file_exists($videoPath)) {
            error_log("ChatBoxAPI: Video file not found: $videoPath");
            return null;
        }

        $url = $this->baseUrl . '/api/upload-video';

        $cfile = new CURLFile($videoPath, mime_content_type($videoPath), basename($videoPath));

        $postData = [
            'file' => $cfile,
            'skill' => $skill
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("ChatBoxAPI upload error: $error");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("ChatBoxAPI upload failed with HTTP $httpCode: $response");
            return null;
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ChatBoxAPI: Invalid JSON response: $response");
            return null;
        }

        return $result;
    }


    public function analyzeVideo(string $sessionId, string $skill = 'drive_forehand'): ?array {
        $url = $this->baseUrl . '/api/analyze-video';

        $postData = json_encode([
            'session_id' => $sessionId,
            'skill' => $skill
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("ChatBoxAPI analyze error: $error");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("ChatBoxAPI analyze failed with HTTP $httpCode: $response");
            return null;
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ChatBoxAPI: Invalid JSON response: $response");
            return null;
        }

        return $result;
    }


    public function getFeedback(string $sessionId, string $skill = 'drive_forehand'): ?array {
        $url = $this->baseUrl . '/api/chat';

        $postData = json_encode([
            'session_id' => $sessionId,
            'skill' => $skill
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("ChatBoxAPI chat error: $error");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("ChatBoxAPI chat failed with HTTP $httpCode: $response");
            return null;
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ChatBoxAPI: Invalid JSON response: $response");
            return null;
        }

        return $result;
    }


    public function runFullAnalysis(string $videoPath, string $skill = 'drive_forehand'): ?array {
        $uploadResult = $this->uploadVideo($videoPath, $skill);
        if (!$uploadResult || !isset($uploadResult['session_id'])) {
            return null;
        }

        $sessionId = $uploadResult['session_id'];

        $analyzeResult = $this->analyzeVideo($sessionId, $skill);
        if (!$analyzeResult) {
            return [
                'success' => false,
                'session_id' => $sessionId,
                'error' => 'Analysis failed',
                'upload_result' => $uploadResult
            ];
        }

        $feedbackResult = $this->getFeedback($sessionId, $skill);

        return [
            'success' => true,
            'session_id' => $sessionId,
            'upload' => $uploadResult,
            'analysis' => $analyzeResult,
            'feedback' => $feedbackResult
        ];
    }
}
