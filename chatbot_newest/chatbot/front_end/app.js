const API_BASE_URL = window.API_BASE_URL || (
    window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
        ? 'http://localhost:8000/api'
        : `${window.location.origin}/api`
);
const ANALYSIS_TIMEOUT_MINUTES = 20;

console.log('=== Pickleball Chatbot Frontend Loaded ===');
console.log('API Base URL:', API_BASE_URL);

window.addEventListener('error', function(e) {
    console.error('=== GLOBAL ERROR HANDLER ===');
    console.error('Error:', e.error);
    console.error('Message:', e.message);
    console.error('File:', e.filename);
    console.error('Line:', e.lineno);
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('=== UNHANDLED PROMISE REJECTION ===');
    console.error('Reason:', e.reason);
    console.error('Promise:', e.promise);
});

let sessionId = null;
let currentStep = 0;

const videoFileInput = document.getElementById('videoFile');
const skillSelect = document.getElementById('skill');
const analyzeBtn = document.getElementById('analyzeBtn');
const resetBtn = document.getElementById('resetBtn');
const statusDiv = document.getElementById('status');
const progressSteps = document.getElementById('progressSteps');
const feedbackSection = document.getElementById('feedbackSection');
const feedbackText = document.getElementById('feedbackText');
const fileName = document.getElementById('fileName');
const detectedSkillDiv = document.getElementById('detectedSkill');

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachFileInputHandler);
} else {
    attachFileInputHandler();
}


function showStatus(message, type = 'info') {
    statusDiv.textContent = message;
    statusDiv.className = `status ${type} show`;

    if (type === 'success') {
        setTimeout(() => {
            statusDiv.classList.remove('show');
        }, 5000);
    }
}

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

function resetProgress() {
    for (let i = 1; i <= 3; i++) {
        const stepElement = document.getElementById(`step${i}`);
        if (stepElement) {
            stepElement.className = 'step';
            stepElement.innerHTML = stepElement.innerHTML.replace('✅', `${i}️⃣`).replace(' - Completed', '...');
        }
    }
    if (detectedSkillDiv) {
        detectedSkillDiv.style.display = 'none';
    }
}

async function startAnalysis() {
    console.log('=== startAnalysis() FUNCTION EXECUTING ===');
    console.log('Timestamp:', new Date().toISOString());

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

    if (!file) {
        console.error('ERROR: No file selected!');
        console.error('File input element:', fileInput);
        console.error('File input files array:', fileInput ? fileInput.files : 'fileInput is null');
        showStatus('Please select a video file first! Click "Click to select video file" and choose a video.', 'error');
        return;
    }

    console.log('File validation passed:', file.name, file.size, 'bytes');

    if (file.size > 50 * 1024 * 1024) {
        showStatus('File too large! Maximum size is 50MB.', 'error');
        return;
    }

    analyzeBtn.disabled = true;
    analyzeBtn.innerHTML = '<span class="loading"></span> Processing...';
    progressSteps.style.display = 'block';
    resetProgress();
    feedbackSection.classList.remove('show');

    try {
        updateProgress(1, 'active');
        showStatus('Uploading video...', 'info');

        const uploadResult = await uploadVideo(file, skill);

        if (!uploadResult || !uploadResult.session_id) {
            throw new Error('Failed to upload video');
        }

        sessionId = uploadResult.session_id;

        if (uploadResult.detected_skill && uploadResult.confidence !== null) {
            const detectedName = uploadResult.detected_skill_name ||
                (uploadResult.detected_skill === 'drive_forehand' ? 'Drive Forehand' : 'Drive Two-Handed Backhand');
            const confidencePercent = (uploadResult.confidence * 100).toFixed(0);

            if (detectedSkillDiv) {
                detectedSkillDiv.style.display = 'block';
                detectedSkillDiv.innerHTML = `🎯 Detected Skill: <strong>${detectedName}</strong> (${confidencePercent}% confidence)`;
                detectedSkillDiv.style.color = '#2e7d32';
                detectedSkillDiv.style.background = '#e8f5e9';
            }

            showStatus(`Video uploaded! Detected: ${detectedName} (${confidencePercent}% confidence)`, 'success');

            if (skillSelectElement && uploadResult.detected_skill !== skill) {
                skillSelectElement.value = uploadResult.detected_skill;
                console.log(`Auto-updated skill selection to: ${uploadResult.detected_skill}`);
                skill = uploadResult.detected_skill;
            }
        } else {
            if (detectedSkillDiv) {
                detectedSkillDiv.style.display = 'none';
            }
            showStatus('Video uploaded successfully!', 'success');
        }

        updateProgress(1, 'completed');

        updateProgress(2, 'active');
        showStatus(`Analyzing video... BLIP-2 can take several minutes (timeout: ${ANALYSIS_TIMEOUT_MINUTES} min).`, 'info');

        const analysisResult = await analyzeVideo(sessionId, skill);

        if (!analysisResult) {
            throw new Error('Video analysis failed');
        }

        updateProgress(2, 'completed');
        const llmNote = analysisResult.vision_llm_status ? ` ${analysisResult.vision_llm_status}` : '';
        showStatus(`Analysis complete! Extracted ${analysisResult.frame_count} frames.${llmNote}`, 'success');

        await new Promise(resolve => setTimeout(resolve, 500));

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

            console.log('Displaying feedback to user...');
            displayFeedback(feedback);

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

        let errorMessage = error.message || 'Unknown error occurred';
        if (error.message && (error.message.includes('Failed to fetch') || error.message.includes('NetworkError'))) {
            errorMessage = 'Cannot connect to server. Make sure the backend is running at http://localhost:8000';
        } else if (error.message && error.message.includes('skill_mismatch') || error.message.includes('appears to be')) {
            errorMessage = error.message;
            updateProgress(1, '');
            if (videoFileInput) {
                videoFileInput.disabled = false;
            }
        } else if (error.message && error.message.includes('Upload error')) {
            errorMessage = `Upload failed: ${error.message.replace('Upload error: ', '')}`;
        } else if (error.message && error.message.includes('Analysis error')) {
            errorMessage = `Analysis failed: ${error.message.replace('Analysis error: ', '')}`;
        } else if (error.message && error.message.includes('Feedback error')) {
            errorMessage = `Feedback generation failed: ${error.message.replace('Feedback error: ', '')}`;
        }

        console.error('Displaying error to user:', errorMessage);

        showStatus(`❌ Error: ${errorMessage}`, 'error');

        setTimeout(() => {
            if (statusDiv.textContent.includes('Error')) {
                statusDiv.classList.add('show'); // Keep showing
            }
        }, 100);

        if (analyzeBtn) {
            analyzeBtn.disabled = false;
            analyzeBtn.innerHTML = '🚀 Start Analysis';
            analyzeBtn.style.display = 'block';
        }


        if (statusDiv) {
            statusDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

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
                if (typeof errorDetail === 'string' && (errorDetail.includes('appears to be') || errorDetail.includes('selected'))) {
                    throw new Error(`skill_mismatch: ${errorDetail}`);
                }
            } catch (e) {
                if (e.message && e.message.includes('skill_mismatch')) {
                    throw e; // Re-throw skill mismatch errors
                }
                errorDetail = `Server returned ${response.status}: ${response.statusText}`;
            }
            throw new Error(errorDetail);
        }

        const data = await response.json();
        if (!data.session_id) {
            throw new Error('Server did not return a session ID');
        }
        return {
            session_id: data.session_id,
            detected_skill: data.detected_skill || null,
            confidence: data.confidence || null,
            detected_skill_name: data.detected_skill_name || null
        };
    } catch (error) {
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            throw new Error('Cannot connect to server. Is the backend running?');
        }
        throw error;
    }
}

async function analyzeVideo(sessionId, skill) {
    try {
        const controller = new AbortController();
        const timeoutMs = ANALYSIS_TIMEOUT_MINUTES * 60 * 1000;
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

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
            throw new Error(`Analysis timed out after ${ANALYSIS_TIMEOUT_MINUTES} minutes. BLIP-2 may still be loading or the video is taking longer than expected. Please try again.`);
        }
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            throw new Error('Cannot connect to server. Is the backend running?');
        }
        throw new Error(`Analysis error: ${error.message}`);
    }
}

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

function displayFeedback(feedback) {
    feedbackText.textContent = feedback;
    feedbackSection.classList.add('show');

    feedbackSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

window.resetForm = function() {
    return resetFormFunction();
};

function resetForm() {
    videoFileInput.value = '';
    fileName.textContent = '';
    if (detectedSkillDiv) {
        detectedSkillDiv.style.display = 'none';
    }
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
