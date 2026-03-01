@echo off
REM Windows batch script to set up OpenAI LLM provider
REM Usage: Run this script, then start your server

echo ========================================
echo Setting up OpenAI LLM Provider
echo ========================================
echo.

REM Set environment variables for current session
set LLM_PROVIDER=openai
set LLM_MODEL=gpt-4

echo Please enter your OpenAI API key:
echo (Get it from https://platform.openai.com/api-keys)
set /p OPENAI_API_KEY="API Key: "

if "%OPENAI_API_KEY%"=="" (
    echo ERROR: API key cannot be empty!
    pause
    exit /b 1
)

echo.
echo ========================================
echo Configuration set!
echo ========================================
echo Provider: %LLM_PROVIDER%
echo Model: %LLM_MODEL%
echo API Key: %OPENAI_API_KEY:~0,10%... (hidden)
echo.
echo IMPORTANT: These variables are set for THIS terminal session only.
echo To make them permanent, use System Environment Variables.
echo.
echo Now start your server with: python main.py
echo.
pause









