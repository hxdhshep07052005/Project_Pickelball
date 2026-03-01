#!/bin/bash
# Linux/Mac shell script to set up OpenAI LLM provider
# Usage: source setup_openai.sh (or . setup_openai.sh)

echo "========================================"
echo "Setting up OpenAI LLM Provider"
echo "========================================"
echo ""

# Set environment variables
export LLM_PROVIDER=openai
export LLM_MODEL=gpt-4

# Prompt for API key
echo "Please enter your OpenAI API key:"
echo "(Get it from https://platform.openai.com/api-keys)"
read -s OPENAI_API_KEY

if [ -z "$OPENAI_API_KEY" ]; then
    echo "ERROR: API key cannot be empty!"
    exit 1
fi

export OPENAI_API_KEY

echo ""
echo "========================================"
echo "Configuration set!"
echo "========================================"
echo "Provider: $LLM_PROVIDER"
echo "Model: $LLM_MODEL"
echo "API Key: ${OPENAI_API_KEY:0:10}... (hidden)"
echo ""
echo "IMPORTANT: These variables are set for THIS terminal session only."
echo "To make them permanent, add to ~/.bashrc or ~/.zshrc"
echo ""
echo "Now start your server with: python main.py"
echo ""









