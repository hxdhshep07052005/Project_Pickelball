<?php
declare(strict_types=1);

/**
 * Backend handler for chat messages
 * Handles user questions about analysis results and provides coaching advice
 */

// Disable error display, log errors instead
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Start output buffering to catch any unexpected output
ob_start();

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication and database connection
try {
    require_once __DIR__ . '/../../user/backend/require_auth.php';
    require_once __DIR__ . '/../../user/backend/bootstrap.php';
} catch (Throwable $e) {
    ob_end_clean();
    error_log("Chat handler initialization error: " . $e->getMessage());
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$userMessage = trim($input['message'] ?? '');
$analysisId = $input['analysis_id'] ?? null;

if (!$sessionId || !$userMessage) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing session_id or message']);
    exit;
}

// Get analysis data from database (try video_analyses first, then action_predictions)
try {
    error_log("Chat handler: Looking for session_id: " . $sessionId . " for user_id: " . $authUser['id']);
    
    // Try video_analyses table first
    $stmt = $pdo->prepare('SELECT id, session_id, coaching_feedback, raw_feedback, techniques_detected FROM video_analyses WHERE session_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$sessionId, $authUser['id']]);
    $analysis = $stmt->fetch();
    
    if ($analysis) {
        error_log("Chat handler: Found analysis in video_analyses table");
    } else {
        error_log("Chat handler: Not found in video_analyses, trying action_predictions");
    }
    
    // If not found, try action_predictions table
    if (!$analysis) {
        try {
            // First, check if session_id exists at all (without user_id filter)
            $checkStmt = $pdo->prepare('SELECT id, user_id, analysis_session_id FROM action_predictions WHERE analysis_session_id = ? LIMIT 5');
            $checkStmt->execute([$sessionId]);
            $allMatches = $checkStmt->fetchAll();
            error_log("Chat handler: Found " . count($allMatches) . " records with session_id: " . $sessionId);
            foreach ($allMatches as $match) {
                error_log("  - ID: " . $match['id'] . ", user_id: " . $match['user_id'] . ", analysis_session_id: " . $match['analysis_session_id']);
            }
            
            // Now query with user_id filter
            $stmt = $pdo->prepare('SELECT id, analysis_session_id as session_id, analysis_coaching_feedback as coaching_feedback, analysis_raw_feedback as raw_feedback, predicted_class as techniques_detected FROM action_predictions WHERE analysis_session_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$sessionId, $authUser['id']]);
            $analysis = $stmt->fetch();
            
            if ($analysis) {
                error_log("Chat handler: Found analysis in action_predictions table");
            } else {
                error_log("Chat handler: Not found in action_predictions with user_id filter. Session ID: " . $sessionId . ", User ID: " . $authUser['id']);
                
                // Check if session_id exists at all (for debugging)
                $checkStmt = $pdo->prepare('SELECT COUNT(*) as count FROM action_predictions WHERE analysis_session_id = ?');
                $checkStmt->execute([$sessionId]);
                $checkResult = $checkStmt->fetch();
                error_log("Chat handler: Total count with session_id (any user): " . ($checkResult['count'] ?? 0));
            }
        } catch (PDOException $e) {
            error_log("Chat handler: Error querying action_predictions: " . $e->getMessage());
            error_log("Chat handler: Session ID: " . $sessionId . ", User ID: " . $authUser['id']);
            error_log("Chat handler: Stack trace: " . $e->getTraceAsString());
            $analysis = null;
        }
    }
    
    if (!$analysis) {
        ob_end_clean();
        
        // Get more debug info before returning error
        $debugInfo = [
            'session_id' => $sessionId,
            'user_id' => $authUser['id'] ?? null,
            'searched_tables' => ['video_analyses', 'action_predictions']
        ];
        
        // Try to get any matching records (for debugging)
        try {
            $debugStmt = $pdo->prepare('SELECT id, user_id, analysis_session_id, created_at FROM action_predictions WHERE analysis_session_id = ? LIMIT 5');
            $debugStmt->execute([$sessionId]);
            $debugRecords = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
            $debugInfo['matching_records'] = $debugRecords;
            $debugInfo['matching_count'] = count($debugRecords);
        } catch (Exception $e) {
            $debugInfo['debug_query_error'] = $e->getMessage();
        }
        
        error_log("Chat handler: 404 - Analysis not found. Debug info: " . json_encode($debugInfo));
        
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => 'Analysis not found for session_id: ' . $sessionId,
            'debug' => $debugInfo
        ]);
        exit;
    }
    
    // Prepare chat context - handle both video_analyses and action_predictions formats
    $rawFeedbackStr = $analysis['raw_feedback'] ?? null;
    $coachingFeedbackStr = $analysis['coaching_feedback'] ?? null;
    
    // Decode raw_feedback (could be JSON string or null)
    $feedbackData = [];
    if ($rawFeedbackStr) {
        if (is_string($rawFeedbackStr)) {
            $decoded = json_decode($rawFeedbackStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $feedbackData = $decoded;
            }
        } elseif (is_array($rawFeedbackStr)) {
            $feedbackData = $rawFeedbackStr;
        }
    }
    
    // Decode coaching_feedback (could be JSON string, plain string, or null)
    $coachingFeedback = '';
    if ($coachingFeedbackStr && $coachingFeedbackStr !== 'null' && trim($coachingFeedbackStr) !== '') {
        if (is_string($coachingFeedbackStr)) {
            // Try to decode as JSON first
            $decoded = json_decode($coachingFeedbackStr, true);
            if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                // If it's an array or object, convert to string
                if (is_array($decoded)) {
                    if (isset($decoded['text']) && is_string($decoded['text'])) {
                        $coachingFeedback = $decoded['text'];
                    } elseif (isset($decoded['feedback']) && is_string($decoded['feedback'])) {
                        $coachingFeedback = $decoded['feedback'];
                    } else {
                        // Extract all string values from array
                        $stringValues = array_filter($decoded, function($item) { return is_string($item) && trim($item) !== ''; });
                        if (!empty($stringValues)) {
                            $coachingFeedback = implode("\n", $stringValues);
                        } else {
                            $coachingFeedback = $coachingFeedbackStr; // Fallback to original
                        }
                    }
                } elseif (is_string($decoded)) {
                    $coachingFeedback = $decoded;
                } else {
                    $coachingFeedback = $coachingFeedbackStr; // Keep as string
                }
            } else {
                $coachingFeedback = $coachingFeedbackStr; // Plain string
            }
        } else {
            $coachingFeedback = (string)$coachingFeedbackStr;
        }
    }
    
    // Build messages for LLM
    $messages = [];
    
    try {
        // System prompt for chat
        $systemPrompt = "You are a professional pickleball coach providing personalized training advice. ";
        $systemPrompt .= "You have just analyzed a player's video and provided initial feedback. ";
        $systemPrompt .= "The player is now asking follow-up questions. ";
        $systemPrompt .= "IMPORTANT: Answer the player's specific question directly and comprehensively. ";
        $systemPrompt .= "If they ask about daily routine, provide a detailed daily routine. ";
        $systemPrompt .= "If they ask about practice schedule, provide a weekly schedule. ";
        $systemPrompt .= "If they ask about improvement, provide specific improvement steps. ";
        $systemPrompt .= "Always reference the analysis feedback when relevant, but answer their actual question first. ";
        $systemPrompt .= "Provide clear, actionable, and encouraging advice. Be specific about exercises, timelines, and practice frequency. ";
        $systemPrompt .= "Keep responses informative and detailed (200-400 words).";
        
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];
        
        // Add initial analysis context
        $contextMessage = "Initial Analysis Results:\n";
        if (!empty($coachingFeedback)) {
            $contextMessage .= "Coaching Feedback: " . $coachingFeedback . "\n\n";
        }
        
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
    } catch (Exception $e) {
        error_log("Error building messages: " . $e->getMessage());
        // Continue with minimal messages
        $messages = [
            ['role' => 'system', 'content' => "You are a professional pickleball coach."],
            ['role' => 'user', 'content' => $userMessage]
        ];
    }
    
    // Call Python script to get LLM response
    $chatbotDir = __DIR__ . '/../../chatbot/back_end';
    $pythonScript = $chatbotDir . '/chat_response.py';
    
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
                chdir($chatbotDir);
                
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
    
    // If we don't have a response yet, provide context-aware fallback based on actual feedback
    if (!$response || $error) {
        // Log error but still provide helpful response based on actual feedback
        error_log("Chat error (using fallback): " . $error);
        
        // Build response based on actual feedback from analysis
        $response = "";
        
        // Start with specific feedback if available
        if (!empty($coachingFeedback)) {
            $coachingText = is_string($coachingFeedback) ? $coachingFeedback : json_encode($coachingFeedback);
            $response .= "Based on your video analysis:\n\n";
            $response .= $coachingText . "\n\n";
        }
        
        // Add specific issues if available
        if (!empty($feedbackData) && is_array($feedbackData)) {
            $response .= "Key areas to focus on:\n\n";
            foreach ($feedbackData as $item) {
                if (isset($item['issue']) && isset($item['tip'])) {
                    $response .= "• " . $item['issue'] . ": " . $item['tip'] . "\n";
                }
            }
            $response .= "\n";
        }
        
        // Add question-specific advice
        if (stripos($userMessage, 'improve') !== false || stripos($userMessage, 'better') !== false) {
            $response .= "To improve, I recommend:\n";
            $response .= "1. Focus on the specific issues mentioned above\n";
            $response .= "2. Practice 15-30 minutes daily with proper form\n";
            $response .= "3. Record yourself regularly to track progress\n";
            $response .= "4. Work on one aspect at a time\n\n";
            $response .= "You should see improvement within 2-4 weeks of consistent practice.";
        } elseif (stripos($userMessage, 'schedule') !== false || stripos($userMessage, 'practice') !== false) {
            $response .= "Recommended practice schedule:\n";
            $response .= "• Daily: 15-30 minutes of focused practice\n";
            $response .= "• 3-4 times per week: Shadow practice with video reference\n";
            $response .= "• Weekly: Record and analyze your technique\n";
            $response .= "• Rest: Take 1-2 days off per week\n\n";
            $response .= "Focus on the issues identified in your analysis for best results.";
        } elseif (stripos($userMessage, 'routine') !== false || stripos($userMessage, 'daily') !== false) {
            $response .= "Daily practice routine:\n";
            $response .= "1. Warm-up (5 min): Light stretching\n";
            $response .= "2. Technique focus (10-15 min): Work on issues from analysis\n";
            $response .= "3. Shadow practice (5-10 min): Mimic proper form\n";
            $response .= "4. Cool-down (5 min): Review progress\n\n";
            $response .= "Focus on the specific areas mentioned in your feedback.";
        } elseif (stripos($userMessage, 'time') !== false || stripos($userMessage, 'long') !== false || stripos($userMessage, 'when') !== false) {
            $response .= "Timeline for improvement:\n";
            $response .= "• Week 1-2: Focus on correcting form issues\n";
            $response .= "• Week 3-4: Muscle memory developing\n";
            $response .= "• Week 5-8: Noticeable improvement\n";
            $response .= "• Month 3+: Significant improvement\n\n";
            $response .= "Stay consistent with the feedback provided above.";
        } elseif (stripos($userMessage, 'mistake') !== false || stripos($userMessage, 'error') !== false || stripos($userMessage, 'wrong') !== false) {
            $response .= "Common mistakes to avoid:\n";
            $response .= "• Stopping your swing at contact - always follow through\n";
            $response .= "• Using only your arms - engage your core and rotate your body\n";
            $response .= "• Rushing the swing - build speed gradually\n";
            $response .= "• Poor balance - maintain a stable, athletic stance\n";
            $response .= "• Inconsistent practice - consistency is key\n\n";
            $response .= "Focus on the specific issues from your analysis to avoid these mistakes.";
        } elseif (stripos($userMessage, 'drill') !== false || stripos($userMessage, 'exercise') !== false || stripos($userMessage, 'practice') !== false) {
            $response .= "Effective drills and exercises:\n";
            $response .= "• Shadow swings: Practice form without a ball, focusing on technique\n";
            $response .= "• Wall practice: Hit against a wall to improve consistency\n";
            $response .= "• Slow motion swings: Break down each phase of your swing\n";
            $response .= "• Mirror work: Check your form and body position\n";
            $response .= "• Video analysis: Record and review regularly\n\n";
            $response .= "Combine these drills with the specific feedback from your analysis.";
        } elseif (stripos($userMessage, 'warm') !== false || stripos($userMessage, 'warmup') !== false || stripos($userMessage, 'warm-up') !== false) {
            $response .= "Proper warm-up routine:\n";
            $response .= "1. Light cardio (3-5 min): Jogging or jumping jacks\n";
            $response .= "2. Dynamic stretching (5 min): Arm circles, leg swings, torso twists\n";
            $response .= "3. Sport-specific movements (3-5 min): Shadow swings, footwork drills\n";
            $response .= "4. Gradual intensity: Start slow and build up\n\n";
            $response .= "A good warm-up prevents injury and improves performance.";
        } elseif (stripos($userMessage, 'strength') !== false || stripos($userMessage, 'fitness') !== false || stripos($userMessage, 'exercise') !== false) {
            $response .= "Strength exercises for pickleball:\n";
            $response .= "• Core: Planks, Russian twists, dead bugs\n";
            $response .= "• Upper body: Push-ups, rows, shoulder rotations\n";
            $response .= "• Lower body: Squats, lunges, calf raises\n";
            $response .= "• Rotational: Medicine ball twists, cable rotations\n";
            $response .= "• Balance: Single-leg stands, stability ball exercises\n\n";
            $response .= "Strength training 2-3 times per week complements your technique practice.";
        } elseif (stripos($userMessage, 'mental') !== false || stripos($userMessage, 'mind') !== false || stripos($userMessage, 'focus') !== false) {
            $response .= "Mental preparation tips:\n";
            $response .= "• Visualization: Picture yourself executing perfect technique\n";
            $response .= "• Focus on process, not outcome: Concentrate on form, not results\n";
            $response .= "• Breathing: Use deep breaths to stay calm and focused\n";
            $response .= "• Positive self-talk: Encourage yourself during practice\n";
            $response .= "• Set small goals: Break improvement into manageable steps\n\n";
            $response .= "Mental preparation enhances physical performance.";
        } elseif (stripos($userMessage, 'strategy') !== false || stripos($userMessage, 'match') !== false || stripos($userMessage, 'game') !== false) {
            $response .= "Match strategy tips:\n";
            $response .= "• Consistency over power: Focus on placement and control\n";
            $response .= "• Use your strengths: Play to techniques you've mastered\n";
            $response .= "• Adapt to opponents: Adjust your game plan as needed\n";
            $response .= "• Stay patient: Don't force shots, wait for opportunities\n";
            $response .= "• Practice under pressure: Simulate match conditions\n\n";
            $response .= "Combine solid technique with smart strategy for success.";
        } elseif (stripos($userMessage, 'recover') !== false || stripos($userMessage, 'rest') !== false || stripos($userMessage, 'rest') !== false) {
            $response .= "Recovery tips after practice:\n";
            $response .= "• Cool down: 5-10 minutes of light activity\n";
            $response .= "• Stretching: Focus on shoulders, arms, and legs\n";
            $response .= "• Hydration: Drink plenty of water\n";
            $response .= "• Rest: Allow 1-2 rest days per week\n";
            $response .= "• Sleep: Get 7-9 hours for optimal recovery\n\n";
            $response .= "Proper recovery helps prevent injury and improves performance.";
        } else {
            // Generic response but reference actual feedback
            if (!empty($coachingFeedback) || !empty($feedbackData)) {
                $response .= "Please review the feedback above. For best results, practice 15-30 minutes daily, focusing on one technique at a time. ";
                $response .= "You should see improvement within 2-4 weeks of consistent practice.";
            } else {
                $response .= "Based on your analysis, practice 15-30 minutes daily, focusing on one technique at a time. ";
                $response .= "You should see improvement within 2-4 weeks of consistent practice.";
            }
        }
    }
    
    // Ensure we always have a response
    if (!$response || trim($response) === '') {
        // Last resort: use actual feedback if available
        if (!empty($coachingFeedback)) {
            $coachingText = is_string($coachingFeedback) ? $coachingFeedback : json_encode($coachingFeedback);
            $response = "Based on your video analysis:\n\n" . $coachingText . "\n\n";
            $response .= "For best results, practice 15-30 minutes daily, focusing on one technique at a time.";
        } else {
            $response = "Thank you for your question! Based on the analysis, I recommend focusing on the key areas mentioned in the feedback. ";
            $response .= "For best results, practice 15-30 minutes daily, focusing on one technique at a time. ";
            $response .= "You should see improvement within 2-4 weeks of consistent practice.";
        }
    }
    
    // Clear output buffer and return response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'response' => $response,
        'session_id' => $sessionId
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    ob_end_clean();
    error_log("Chat handler database error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Session ID: " . ($sessionId ?? 'null'));
    error_log("User ID: " . ($authUser['id'] ?? 'null'));
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error',
        'debug' => [
            'session_id' => $sessionId ?? null,
            'error_message' => $e->getMessage()
        ]
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    error_log("Chat handler fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Session ID: " . ($sessionId ?? 'null'));
    error_log("User ID: " . ($authUser['id'] ?? 'null'));
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Internal server error: ' . $e->getMessage(),
        'debug' => [
            'session_id' => $sessionId ?? null,
            'error_type' => get_class($e),
            'error_message' => $e->getMessage()
        ]
    ]);
}

