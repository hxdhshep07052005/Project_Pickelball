# Quick LLM Setup Script for Windows PowerShell
# Run this script to configure a real LLM provider

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "LLM Provider Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Choose your LLM provider:" -ForegroundColor Yellow
Write-Host "1. OpenAI (GPT-4, GPT-3.5-turbo) - Recommended"
Write-Host "2. Anthropic (Claude)"
Write-Host "3. Ollama (Local, Free)"
Write-Host ""

$choice = Read-Host "Enter choice (1-3)"

if ($choice -eq "1") {
    Write-Host ""
    Write-Host "Setting up OpenAI..." -ForegroundColor Green
    
    # Check if openai package is installed
    try {
        python -c "import openai" 2>$null
        Write-Host "[OK] OpenAI library is installed" -ForegroundColor Green
    } catch {
        Write-Host "[INFO] Installing OpenAI library..." -ForegroundColor Yellow
        pip install openai
    }
    
    $apiKey = Read-Host "Enter your OpenAI API key (starts with sk-)" -AsSecureString
    $apiKeyPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($apiKey))
    
    $model = Read-Host "Enter model name (default: gpt-3.5-turbo) [gpt-4, gpt-3.5-turbo]"
    if ([string]::IsNullOrWhiteSpace($model)) {
        $model = "gpt-3.5-turbo"
    }
    
    # Set environment variables for current session
    $env:LLM_PROVIDER = "openai"
    $env:LLM_MODEL = $model
    $env:OPENAI_API_KEY = $apiKeyPlain
    
    Write-Host ""
    Write-Host "[OK] Environment variables set for current session" -ForegroundColor Green
    Write-Host ""
    Write-Host "To make these permanent, run:" -ForegroundColor Yellow
    Write-Host '[System.Environment]::SetEnvironmentVariable("LLM_PROVIDER", "openai", "User")' -ForegroundColor Gray
    Write-Host "[System.Environment]::SetEnvironmentVariable(`"LLM_MODEL`", `"$model`", `"User`")" -ForegroundColor Gray
    Write-Host '[System.Environment]::SetEnvironmentVariable("OPENAI_API_KEY", "<your_key_here>", "User")' -ForegroundColor Gray
    Write-Host "Tip: avoid printing real keys in terminal history or screenshots." -ForegroundColor DarkYellow
    
} elseif ($choice -eq "2") {
    Write-Host ""
    Write-Host "Setting up Anthropic (Claude)..." -ForegroundColor Green
    
    try {
        python -c "import anthropic" 2>$null
        Write-Host "[OK] Anthropic library is installed" -ForegroundColor Green
    } catch {
        Write-Host "[INFO] Installing Anthropic library..." -ForegroundColor Yellow
        pip install anthropic
    }
    
    $apiKey = Read-Host "Enter your Anthropic API key (starts with sk-ant-)" -AsSecureString
    $apiKeyPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($apiKey))
    
    $model = Read-Host "Enter model name (default: claude-3-opus-20240229) [claude-3-opus-20240229, claude-3-sonnet-20240229]"
    if ([string]::IsNullOrWhiteSpace($model)) {
        $model = "claude-3-opus-20240229"
    }
    
    $env:LLM_PROVIDER = "anthropic"
    $env:LLM_MODEL = $model
    $env:ANTHROPIC_API_KEY = $apiKeyPlain
    
    Write-Host ""
    Write-Host "[OK] Environment variables set for current session" -ForegroundColor Green
    
} elseif ($choice -eq "3") {
    Write-Host ""
    Write-Host "Setting up Ollama (Local)..." -ForegroundColor Green
    Write-Host "[INFO] Make sure Ollama is installed and running" -ForegroundColor Yellow
    Write-Host "Download from: https://ollama.ai/" -ForegroundColor Yellow
    Write-Host ""
    
    $model = Read-Host "Enter model name (default: llama2) [llama2, mistral, codellama]"
    if ([string]::IsNullOrWhiteSpace($model)) {
        $model = "llama2"
    }
    
    $env:LLM_PROVIDER = "ollama"
    $env:LLM_MODEL = $model
    
    Write-Host ""
    Write-Host "[OK] Environment variables set for current session" -ForegroundColor Green
    Write-Host "[INFO] Make sure Ollama is running: ollama serve" -ForegroundColor Yellow
    
} else {
    Write-Host "[ERROR] Invalid choice" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Verifying configuration..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

python check_llm_config.py

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "IMPORTANT: Restart your backend server for changes to take effect!" -ForegroundColor Yellow
Write-Host "Run: python main.py" -ForegroundColor Yellow


