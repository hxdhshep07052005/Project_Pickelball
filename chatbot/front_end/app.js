// API Configuration
const API_BASE_URL = 'http://localhost:8000/api';

// Log that script loaded
console.log('=== Pickleball Chatbot Frontend Loaded ===');
console.log('API Base URL:', API_BASE_URL);

// Global error handler
window.addEventListener('error', function(e) {
    console.error('=== GLOBAL ERROR HANDLER ===');
    console.error('Error:', e.error);
    console.error('Message:', e.message);
    console.error('File:', e.filename);
    console.error('Line:', e.lineno);
});

// Global unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(e) {
    console.error('=== UNHANDLED PROMISE REJECTION ===');
    console.error('Reason:', e.reason);
    console.error('Promise:', e.promise);
});

// Global state
let sessionId = null;
let currentStep = 0;

// DOM Elements
const videoFileInput = document.getElementById('videoFile');
const skillSelect = document.getElementById('skill');
const analyzeBtn = document.getElementById('analyzeBtn');
const resetBtn = document.getElementById('resetBtn');
const statusDiv = document.getElementById('status');
const progressSteps = document.getElementById('progressSteps');
const feedbackSection = document.getElementById('feedbackSection');
const feedbackText = document.getElementById('feedbackText');
const fileName = document.getElementById('fileName');

// File input handler - attach after DOM is ready
function attachFileInputHandler() {
    if (videoFileInput) {
        videoFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                if (fileName) {
                    fileName.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                    fileName.style.color = '#667eea';
                }
                console.log('File selected:', file.name);
            }
        });
        console.log('File input handler attached');
    }
}

// Attach handler after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachFileInputHandler);
} else {
    attachFileInputHandler();
}

// Note: startAnalysis function will be defined below and available globally

// Show status message
function showStatus(message, type = 'info') {
    statusDiv.textContent = message;
    statusDiv.className = `status ${type} show`;
    
    // Auto-hide success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.classList.remove('show');
        }, 5000);
    }
}

// Update progress steps
function updateProgress(step, status) {
    const stepElement = document.getElementById(`step${step}`);
    if (stepElement) {
        stepElement.className = `step ${status}`;
        
        if (status === 'active') {
            stepElement.innerHTML = `<span class="step-icon">${step}️⃣</span><span class="loading"></span> ${stepElement.textContent.replace(/[0-9]️⃣\s*/, '').replace('...', '')}...`;
        } else if (status === 'completed') {
            stepElement.innerHTML = `<span class="step-icon">✅</span><span>${stepElement.textContent.replace(/[0-9]️⃣\s*/, '').replace('...', '')} - Completed</span>`;
        }
    }
}

// Reset all progress steps
function resetProgress() {
    for (let i = 1; i <= 3; i++) {
        const stepElement = document.getElementById(`step${i}`);
        if (stepElement) {
            stepElement.className = 'step';
            stepElement.innerHTML = stepElement.innerHTML.replace('✅', `${i}️⃣`).replace(' - Completed', '...');
        }
    }
}

// Main analysis function (internal name)
// Main analysis function - available globally for onclick="startAnalysis()"
async function startAnalysis() {
    console.log('=== startAnalysis() FUNCTION EXECUTING ===');
    console.log('Timestamp:', new Date().toISOString());
    
    // Re-read DOM elements to ensure we have the latest values
    const fileInput = document.getElementById('videoFile');
    const skillSelectElement = document.getElementById('skill');
    
    if (!fileInput) {
        console.error('ERROR: videoFile input not found!');
        showStatus('Error: File input not found. Please refresh the page.', 'error');
        return;
    }
    
    const file = fileInput.files[0];
    const skill = skillSelectElement ? skillSelectElement.value : 'drive_forehand';
    
    console.log('File input element:', fileInput);
    console.log('File input files:', fileInput.files);
    console.log('File selected:', file ? file.name : 'NONE');
    console.log('File size:', file ? file.size : 'N/A');
    console.log('Skill selected:', skill);

    // Validation
    if (!file) {
        console.error('ERROR: No file selected!');
        console.error('File input element:', fileInput);
        console.error('File input files array:', fileInput ? fileInput.files : 'fileInput is null');
        showStatus('Please select a video file first! Click "Click to select video file" and choose a video.', 'error');
        return;
    }
    
    console.log('File validation passed:', file.name, file.size, 'bytes');

    // Validate file size (max 50MB)
    if (file.size > 50 * 1024 * 1024) {
        showStatus('File too large! Maximum size is 50MB.', 'error');
        return;
    }

    // Disable button and show progress
    analyzeBtn.disabled = true;
    analyzeBtn.innerHTML = '<span class="loading"></span> Processing...';
    progressSteps.style.display = 'block';
    resetProgress();
    feedbackSection.classList.remove('show');

    try {
        // Step 1: Upload video
        updateProgress(1, 'active');
        showStatus('Uploading video...', 'info');
        
        sessionId = await uploadVideo(file, skill);
        
        if (!sessionId) {
            throw new Error('Failed to upload video');
        }
        
        updateProgress(1, 'completed');
        showStatus('Video uploaded successfully!', 'success');

        // Step 2: Analyze video
        updateProgress(2, 'active');
        showStatus('Analyzing video... This may take a minute.', 'info');
        
        const analysisResult = await analyzeVideo(sessionId, skill);
        
        if (!analysisResult) {
            throw new Error('Video analysis failed');
        }
        
        updateProgress(2, 'completed');
        showStatus(`Analysis complete! Extracted ${analysisResult.frame_count} frames.`, 'success');

        // Small delay to ensure analysis is fully complete
        await new Promise(resolve => setTimeout(resolve, 500));

        // Step 3: Get coaching feedback
        console.log('=== STARTING STEP 3: Get Coaching Feedback ===');
        console.log('Session ID:', sessionId);
        console.log('Skill:', skill);
        
        updateProgress(3, 'active');
        showStatus('Generating personalized coaching feedback...', 'info');
        
        try {
            console.log('About to call getCoachingFeedback...');
            const feedback = await getCoachingFeedback(sessionId, skill);
            console.log('Successfully received feedback:', feedback);
            
            if (!feedback || feedback.trim() === '') {
                console.error('ERROR: Empty feedback received');
                throw new Error('Server returned empty feedback');
            }
            
            updateProgress(3, 'completed');
            showStatus('Feedback generated successfully!', 'success');

            // Display feedback
            console.log('Displaying feedback to user...');
            displayFeedback(feedback);
            
            // Show reset button
            resetBtn.style.display = 'block';
            analyzeBtn.style.display = 'none';
            
            console.log('=== ALL STEPS COMPLETED SUCCESSFULLY ===');
        } catch (feedbackError) {
            console.error('ERROR in feedback step:', feedbackError);
            throw feedbackError; // Re-throw to be caught by outer catch
        }

    } catch (error) {
        console.error('=== ERROR CAUGHT IN startAnalysis ===');
        console.error('Error object:', error);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        
        // Show detailed error message
        let errorMessage = error.message || 'Unknown error occurred';
        if (error.message && (error.message.includes('Failed to fetch') || error.message.includes('NetworkError'))) {
            errorMessage = 'Cannot connect to server. Make sure the backend is running at http://localhost:8000';
        } else if (error.message && error.message.includes('Upload error')) {
            errorMessage = `Upload failed: ${error.message.replace('Upload error: ', '')}`;
        } else if (error.message && error.message.includes('Analysis error')) {
            errorMessage = `Analysis failed: ${error.message.replace('Analysis error: ', '')}`;
        } else if (error.message && error.message.includes('Feedback error')) {
            errorMessage = `Feedback generation failed: ${error.message.replace('Feedback error: ', '')}`;
        }
        
        console.error('Displaying error to user:', errorMessage);
        
        // Show error message prominently
        showStatus(`❌ Error: ${errorMessage}`, 'error');
        
        // Keep error visible for longer
        setTimeout(() => {
            if (statusDiv.textContent.includes('Error')) {
                statusDiv.classList.add('show'); // Keep showing
            }
        }, 100);
        
        // Reset button but keep progress visible so user can see where it failed
        if (analyzeBtn) {
            analyzeBtn.disabled = false;
            analyzeBtn.innerHTML = '🚀 Start Analysis';
            analyzeBtn.style.display = 'block';
        }
        
        // DON'T hide progress steps - let user see where it failed
        // DON'T reset the form - keep the file selected
        // DON'T hide feedback section in case partial data exists
        
        // Scroll to error message
        if (statusDiv) {
            statusDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

// Upload video to server
async function uploadVideo(file, skill) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('skill', skill);

    try {
        const response = await fetch(`${API_BASE_URL}/upload-video`, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            let errorDetail = 'Upload failed';
            try {
                const error = await response.json();
                errorDetail = error.detail || error.message || 'Upload failed';
            } catch (e) {
                errorDetail = `Server returned ${response.status}: ${response.statusText}`;
            }
            throw new Error(errorDetail);
        }

        const data = await response.json();
        if (!data.session_id) {
            throw new Error('Server did not return a session ID');
        }
        return data.session_id;
    } catch (error) {
        // Re-throw with more context
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            throw new Error('Cannot connect to server. Is the backend running?');
        }
        throw error;
    }
}

// Analyze video
async function analyzeVideo(sessionId, skill) {
    try {
        // Create AbortController for timeout (5 minutes for Vision LLM processing)
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5 * 60 * 1000); // 5 minutes
        
        const response = await fetch(`${API_BASE_URL}/analyze-video`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                session_id: sessionId,
                skill: skill
            }),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);

        if (!response.ok) {
            let errorDetail = 'Analysis failed';
            try {
                const error = await response.json();
                errorDetail = error.detail || error.message || 'Analysis failed';
            } catch (e) {
                errorDetail = `Server returned ${response.status}: ${response.statusText}`;
            }
            throw new Error(errorDetail);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        if (error.name === 'AbortError') {
            throw new Error('Analysis timed out. The video analysis is taking longer than expected. Please try again or check if Vision LLM is loading.');
        }
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            throw new Error('Cannot connect to server. Is the backend running?');
        }
        throw new Error(`Analysis error: ${error.message}`);
    }
}

// Get coaching feedback
async function getCoachingFeedback(sessionId, skill) {
    console.log('=== getCoachingFeedback() called ===');
    console.log('Session ID:', sessionId);
    console.log('Skill:', skill);
    console.log('API URL:', `${API_BASE_URL}/chat`);
    
    try {
        const requestBody = {
            session_id: sessionId,
            skill: skill
        };
        console.log('Request body:', JSON.stringify(requestBody));
        
        const response = await fetch(`${API_BASE_URL}/chat`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });

        console.log('Response received!');
        console.log('Response status:', response.status, response.statusText);

        if (!response.ok) {
            let errorDetail = 'Failed to get feedback';
            let errorData = null;
            try {
                errorData = await response.json();
                console.error('Error response JSON:', errorData);
                errorDetail = errorData.detail || errorData.message || 'Failed to get feedback';
            } catch (e) {
                console.error('Failed to parse error response as JSON:', e);
                const text = await response.text();
                console.error('Error response text:', text);
                errorDetail = `Server returned ${response.status}: ${response.statusText}. ${text}`;
            }
            throw new Error(errorDetail);
        }

        const data = await response.json();
        console.log('Response data received:', data);
        console.log('Feedback field exists:', 'feedback' in data);
        console.log('Feedback value:', data.feedback);
        console.log('Feedback type:', typeof data.feedback);
        console.log('Feedback length:', data.feedback ? data.feedback.length : 'N/A');
        
        if (!data.feedback) {
            console.error('ERROR: No feedback field in response!');
            console.error('Full response:', JSON.stringify(data, null, 2));
            throw new Error('Server did not return feedback in response. Response: ' + JSON.stringify(data));
        }
        
        if (typeof data.feedback !== 'string') {
            console.error('ERROR: Feedback is not a string! Type:', typeof data.feedback);
            throw new Error('Server returned invalid feedback format');
        }
        
        console.log('✅ Feedback successfully extracted:', data.feedback.substring(0, 100) + '...');
        return data.feedback;
    } catch (error) {
        console.error('=== getCoachingFeedback ERROR ===');
        console.error('Error type:', error.constructor.name);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        
        if (error.message && (error.message.includes('Failed to fetch') || error.message.includes('NetworkError'))) {
            throw new Error('Cannot connect to server. Is the backend running at http://localhost:8000?');
        }
        throw new Error(`Feedback error: ${error.message}`);
    }
}

// Display feedback to user
function displayFeedback(feedback) {
    feedbackText.textContent = feedback;
    feedbackSection.classList.add('show');
    
    // Scroll to feedback
    feedbackSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Make resetForm available globally
window.resetForm = function() {
    return resetFormFunction();
};

// Reset form - available globally for onclick="resetForm()"
function resetForm() {
    videoFileInput.value = '';
    fileName.textContent = '';
    sessionId = null;
    analyzeBtn.disabled = false;
    analyzeBtn.innerHTML = '🚀 Start Analysis';
    analyzeBtn.style.display = 'block';
    resetBtn.style.display = 'none';
    feedbackSection.classList.remove('show');
    progressSteps.style.display = 'none';
    resetProgress();
    statusDiv.classList.remove('show');
}

