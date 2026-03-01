# Quick LLM Setup Guide

Your system is currently using the **placeholder response**. To use a real LLM for generating feedback, follow these steps:

## Option 1: Quick Setup Script (Windows PowerShell)

1. **Open PowerShell** in the `back_end` folder
2. **Run the setup script**:
   ```powershell
   .\setup_llm.ps1
   ```
3. **Follow the prompts** to choose your provider and enter API keys
4. **Restart your server** after setup

## Option 2: Manual Setup (All Platforms)

### For OpenAI (Easiest - Recommended)

1. **Get an API key**:
   - Go to https://platform.openai.com/api-keys
   - Sign up/login and create a new API key
   - Copy the key (starts with `sk-`)

2. **Install the library**:
   ```bash
   pip install openai
   ```

3. **Set environment variables**:

   **Windows PowerShell:**
   ```powershell
   $env:LLM_PROVIDER="openai"
   $env:LLM_MODEL="gpt-3.5-turbo"  # or "gpt-4" for better quality
   $env:OPENAI_API_KEY="sk-your-key-here"
   ```

   **Windows CMD:**
   ```cmd
   set LLM_PROVIDER=openai
   set LLM_MODEL=gpt-3.5-turbo
   set OPENAI_API_KEY=sk-your-key-here
   ```

   **Linux/Mac:**
   ```bash
   export LLM_PROVIDER=openai
   export LLM_MODEL=gpt-3.5-turbo
   export OPENAI_API_KEY=sk-your-key-here
   ```

4. **Verify configuration**:
   ```bash
   python check_llm_config.py
   ```

5. **Restart your server**:
   ```bash
   python main.py
   ```

### For Anthropic (Claude)

1. **Get an API key** from https://console.anthropic.com/
2. **Install**: `pip install anthropic`
3. **Set variables**:
   ```powershell
   $env:LLM_PROVIDER="anthropic"
   $env:LLM_MODEL="claude-3-opus-20240229"
   $env:ANTHROPIC_API_KEY="sk-ant-your-key-here"
   ```

### For Ollama (Free, Local)

1. **Install Ollama** from https://ollama.ai/
2. **Download a model**:
   ```bash
   ollama pull llama2
   ```
3. **Start Ollama** (usually auto-starts)
4. **Set variables**:
   ```powershell
   $env:LLM_PROVIDER="ollama"
   $env:LLM_MODEL="llama2"
   ```

## Making Environment Variables Permanent (Windows)

### Method 1: PowerShell (Recommended)

Run as Administrator:
```powershell
[System.Environment]::SetEnvironmentVariable("LLM_PROVIDER", "openai", "User")
[System.Environment]::SetEnvironmentVariable("LLM_MODEL", "gpt-3.5-turbo", "User")
[System.Environment]::SetEnvironmentVariable("OPENAI_API_KEY", "sk-your-key", "User")
```

### Method 2: GUI

1. Press `Win + R`, type `sysdm.cpl`, press Enter
2. Go to "Advanced" tab → "Environment Variables"
3. Click "New" under "User variables"
4. Add:
   - `LLM_PROVIDER` = `openai`
   - `LLM_MODEL` = `gpt-3.5-turbo`
   - `OPENAI_API_KEY` = `sk-your-key-here`

## Testing

After setting up, verify it works:

1. **Check configuration**:
   ```bash
   python check_llm_config.py
   ```

2. **Run your server** and upload a video
3. **Check console output** - you should see:
   ```
   📝 Using LLM Provider: OPENAI, Model: gpt-3.5-turbo
   ```
   Instead of:
   ```
   📝 Using Placeholder Response (no LLM configured)
   ```

## Cost Comparison

- **GPT-3.5-turbo**: ~$0.002 per request (cheapest, good quality)
- **GPT-4**: ~$0.03 per request (best quality, more expensive)
- **Claude**: Similar to GPT-4
- **Ollama**: Free (runs locally, no API costs)

## Troubleshooting

### "LLM_PROVIDER environment variable not set"
- Make sure you set the variable in the **same terminal** where you run the server
- Restart the terminal/server after setting variables

### "OPENAI_API_KEY environment variable not set"
- Check that you set the API key correctly
- No quotes needed: `$env:OPENAI_API_KEY="sk-..."` (with quotes is OK)
- Restart server after setting

### Still using placeholder?
- Verify with: `python check_llm_config.py`
- Make sure `LLM_PROVIDER` is lowercase: `"openai"`, not `"OpenAI"`
- Restart your server completely

### API errors?
- Check your API key is valid
- For OpenAI: Check usage at https://platform.openai.com/usage
- For Ollama: Make sure `ollama serve` is running

## Need Help?

See the detailed guide: `SETUP_LLM.md`


