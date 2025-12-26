<?php
/**
 * Backend handler for chat messages
 * Handles user questions about analysis results and provides coaching advice
 */

session_start();
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

// Check authentication
$authUser = getAuthUser();
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$userMessage = trim($input['message'] ?? '');
$analysisId = $input['analysis_id'] ?? null;

if (!$sessionId || !$userMessage) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session_id or message']);
    exit;
}

// Get analysis data from database
try {
    $stmt = $pdo->prepare('SELECT id, session_id, coaching_feedback, raw_feedback, techniques_detected FROM video_analyses WHERE session_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$sessionId, $authUser['id']]);
    $analysis = $stmt->fetch();
    
    if (!$analysis) {
        http_response_code(404);
        echo json_encode(['error' => 'Analysis not found']);
        exit;
    }
    
    // Prepare chat context
    $feedbackData = json_decode($analysis['raw_feedback'] ?? '[]', true) ?: [];
    $coachingFeedback = $analysis['coaching_feedback'] ?? '';
    
    // Build messages for LLM
    $messages = [];
    
    // System prompt for chat
    $systemPrompt = "You are a professional pickleball coach providing personalized training advice. ";
    $systemPrompt .= "You have just analyzed a player's video and provided initial feedback. ";
    $systemPrompt .= "Now the player is asking follow-up questions about how to improve, training schedule, daily practice routines, etc. ";
    $systemPrompt .= "Provide clear, actionable, and encouraging advice. Be specific about exercises, timelines, and practice frequency. ";
    $systemPrompt .= "Keep responses concise but informative (150-300 words).";
    
    $messages[] = [
        'role' => 'system',
        'content' => $systemPrompt
    ];
    
    // Add initial analysis context
    $contextMessage = "Initial Analysis Results:\n";
    $contextMessage .= "Coaching Feedback: " . $coachingFeedback . "\n\n";
    
    if (!empty($feedbackData) && is_array($feedbackData)) {
        $contextMessage .= "Technical Issues Detected:\n";
        foreach ($feedbackData as $item) {
            if (isset($item['issue']) && isset($item['tip'])) {
                $contextMessage .= "- " . $item['issue'] . ": " . $item['tip'] . "\n";
            }
        }
    }
    
    $messages[] = [
        'role' => 'user',
        'content' => $contextMessage
    ];
    
    // Add user's current question
    $messages[] = [
        'role' => 'user',
        'content' => $userMessage
    ];
    
    // Call Python script to get LLM response
    $chatboxDir = __DIR__ . '/../../ChatBox';
    $pythonScript = $chatboxDir . '/chat_response.py';
    
    $response = null;
    $error = null;
    
    if (file_exists($pythonScript)) {
        try {
            // Get Python executable
            $pythonCmd = null;
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $testCommands = ['python', 'py', 'python3'];
                foreach ($testCommands as $cmd) {
                    $testOutput = @shell_exec($cmd . ' --version 2>&1');
                    if ($testOutput && strpos($testOutput, 'Python') !== false) {
                        $pythonCmd = $cmd;
                        break;
                    }
                }
            } else {
                $testOutput = @shell_exec('python3 --version 2>&1');
                if ($testOutput && strpos($testOutput, 'Python') !== false) {
                    $pythonCmd = 'python3';
                } else {
                    $testOutput = @shell_exec('python --version 2>&1');
                    if ($testOutput && strpos($testOutput, 'Python') !== false) {
                        $pythonCmd = 'python';
                    }
                }
            }
            
            if ($pythonCmd) {
                // Create temp file with messages
                $tempFile = tempnam(sys_get_temp_dir(), 'chat_messages_');
                file_put_contents($tempFile, json_encode($messages, JSON_PRETTY_PRINT));
                
                $command = escapeshellarg($pythonCmd) . ' ' . 
                           escapeshellarg($pythonScript) . ' ' . 
                           escapeshellarg($tempFile) . ' 2>&1';
                
                $originalDir = getcwd();
                chdir($chatboxDir);
                
                // Execute command and capture both stdout and stderr
                $output = shell_exec($command);
                
                chdir($originalDir);
                
                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                
                if ($output) {
                    // Log output for debugging
                    error_log("Chat script output: " . substr($output, 0, 500));
                    
                    // Parse JSON response
                    $jsonStart = strpos($output, '{');
                    if ($jsonStart !== false) {
                        $braceCount = 0;
                        $jsonEnd = $jsonStart;
                        for ($i = $jsonStart; $i < strlen($output); $i++) {
                            if ($output[$i] === '{') $braceCount++;
                            if ($output[$i] === '}') {
                                $braceCount--;
                                if ($braceCount === 0) {
                                    $jsonEnd = $i + 1;
                                    break;
                                }
                            }
                        }
                        $jsonOutput = substr($output, $jsonStart, $jsonEnd - $jsonStart);
                        $result = json_decode($jsonOutput, true);
                        
                        if ($result && isset($result['response'])) {
                            $response = $result['response'];
                        } elseif ($result && isset($result['error'])) {
                            $error = $result['error'];
                            error_log("Chat script error: " . $error);
                        } else {
                            $error = 'Failed to get response from LLM. Output: ' . substr($output, 0, 200);
                            error_log("Chat script parse error. Full output: " . $output);
                        }
                    } else {
                        $error = 'Invalid response format from chat script. Output: ' . substr($output, 0, 200);
                        error_log("Chat script invalid format. Full output: " . $output);
                    }
                } else {
                    $error = 'No response from chat script';
                    error_log("Chat script returned no output. Command: " . $command);
                }
            } else {
                $error = 'Python not found';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("Chat handler exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    } else {
        // Fallback: simple response without LLM
        error_log("Chat script file not found: " . $pythonScript);
        $response = null; // Will be set below based on question
    }
    
    // If we don't have a response yet, provide context-aware fallback
    if (!$response || $error) {
        // Log error but still provide helpful response
        error_log("Chat error (using fallback): " . $error);
        // Use fallback response based on user's question
        if (stripos($userMessage, 'improve') !== false || stripos($userMessage, 'better') !== false) {
            $response = "To improve this technique, I recommend:\n\n";
            $response .= "1. Focus on the key issues identified in your analysis\n";
            $response .= "2. Practice 15-30 minutes daily with proper form\n";
            $response .= "3. Record yourself regularly to track progress\n";
            $response .= "4. Work on one aspect at a time for better results\n\n";
            $response .= "You should see noticeable improvement within 2-4 weeks of consistent practice.";
        } elseif (stripos($userMessage, 'schedule') !== false || stripos($userMessage, 'practice') !== false) {
            $response = "For optimal improvement, I recommend this practice schedule:\n\n";
            $response .= "• Daily: 15-30 minutes of focused practice\n";
            $response .= "• 3-4 times per week: Shadow practice with video reference\n";
            $response .= "• Weekly: Record and analyze your technique\n";
            $response .= "• Rest: Take 1-2 days off per week for recovery\n\n";
            $response .= "Consistency is key - even short daily sessions are better than long sporadic ones.";
        } elseif (stripos($userMessage, 'routine') !== false || stripos($userMessage, 'daily') !== false) {
            $response = "Here's a recommended daily practice routine:\n\n";
            $response .= "1. Warm-up (5 min): Light stretching and movement\n";
            $response .= "2. Technique focus (10-15 min): Work on specific issues from analysis\n";
            $response .= "3. Shadow practice (5-10 min): Mimic proper form without equipment\n";
            $response .= "4. Cool-down (5 min): Review what you worked on\n\n";
            $response .= "Remember: Quality over quantity. Focus on proper form throughout.";
        } elseif (stripos($userMessage, 'time') !== false || stripos($userMessage, 'long') !== false || stripos($userMessage, 'when') !== false) {
            $response = "Timeline for improvement:\n\n";
            $response .= "• Week 1-2: Focus on understanding and correcting form issues\n";
            $response .= "• Week 3-4: Begin to see muscle memory developing\n";
            $response .= "• Week 5-8: Noticeable improvement in technique consistency\n";
            $response .= "• Month 3+: Significant improvement with continued practice\n\n";
            $response .= "Everyone progresses at different rates. Stay consistent and patient!";
        } else {
            $response = "Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. ";
            $response .= "For best results, practice 15-30 minutes daily, focusing on one technique at a time. ";
            $response .= "You should see improvement within 2-4 weeks of consistent practice.";
        }
    }
    
    // Ensure we always have a response
    if (!$response || trim($response) === '') {
        $response = "Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. For best results, practice 15-30 minutes daily, focusing on one technique at a time. You should see improvement within 2-4 weeks of consistent practice.";
    }
    
    // Return response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'response' => $response,
        'session_id' => $sessionId
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    error_log("Chat handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

