# How to Switch from Placeholder to Real LLM

This guide shows you how to configure the system to use a real LLM provider instead of the placeholder response.

## Option 1: OpenAI (Recommended - Easiest)

### Step 1: Get an OpenAI API Key

1. Go to https://platform.openai.com/api-keys
2. Sign up or log in
3. Click "Create new secret key"
4. Copy your API key (starts with `sk-...`)

### Step 2: Install OpenAI Library

```bash
pip install openai
```

### Step 3: Set Environment Variables

**Windows (PowerShell):**
```powershell
$env:LLM_PROVIDER="openai"
$env:LLM_MODEL="gpt-4"
$env:OPENAI_API_KEY="sk-your-key-here"
```

**Windows (CMD):**
```cmd
set LLM_PROVIDER=openai
set LLM_MODEL=gpt-4
set OPENAI_API_KEY=sk-your-key-here
```

**Linux/Mac:**
```bash
export LLM_PROVIDER=openai
export LLM_MODEL=gpt-4
export OPENAI_API_KEY=sk-your-key-here
```

### Step 4: Restart Your Server

Stop your current server (Ctrl+C) and restart:
```bash
cd back_end
python main.py
```

### Step 5: Verify Configuration

Run the check script:
```bash
python check_llm_config.py
```

You should see:
```
Provider: openai
Model: gpt-4
Status: Using OpenAI API with model: gpt-4
✓ OPENAI_API_KEY is set
```

---

## Option 2: Anthropic (Claude)

### Step 1: Get an Anthropic API Key

1. Go to https://console.anthropic.com/
2. Sign up or log in
3. Navigate to API Keys
4. Create a new API key
5. Copy your API key

### Step 2: Install Anthropic Library

```bash
pip install anthropic
```

### Step 3: Set Environment Variables

**Windows (PowerShell):**
```powershell
$env:LLM_PROVIDER="anthropic"
$env:LLM_MODEL="claude-3-opus-20240229"
$env:ANTHROPIC_API_KEY="sk-ant-your-key-here"
```

**Windows (CMD):**
```cmd
set LLM_PROVIDER=anthropic
set LLM_MODEL=claude-3-opus-20240229
set ANTHROPIC_API_KEY=sk-ant-your-key-here
```

**Linux/Mac:**
```bash
export LLM_PROVIDER=anthropic
export LLM_MODEL=claude-3-opus-20240229
export ANTHROPIC_API_KEY=sk-ant-your-key-here
```

### Step 4: Restart Your Server

---

## Option 3: Ollama (Local - Free, No API Key Needed)

### Step 1: Install Ollama

Download from: https://ollama.ai/

**Windows:** Download installer and run it
**Linux/Mac:** 
```bash
curl -fsSL https://ollama.ai/install.sh | sh
```

### Step 2: Download a Model

```bash
ollama pull llama2
# or
ollama pull mistral
# or
ollama pull codellama
```

### Step 3: Start Ollama Server

Ollama should start automatically. Verify it's running:
```bash
ollama list
```

### Step 4: Set Environment Variables

**Windows (PowerShell):**
```powershell
$env:LLM_PROVIDER="ollama"
$env:LLM_MODEL="llama2"
```

**Windows (CMD):**
```cmd
set LLM_PROVIDER=ollama
set LLM_MODEL=llama2
```

**Linux/Mac:**
```bash
export LLM_PROVIDER=ollama
export LLM_MODEL=llama2
```

### Step 5: Restart Your Server

---

## Making Environment Variables Permanent

### Windows (PowerShell - Current User)

1. Open PowerShell as Administrator
2. Run:
```powershell
[System.Environment]::SetEnvironmentVariable("LLM_PROVIDER", "openai", "User")
[System.Environment]::SetEnvironmentVariable("LLM_MODEL", "gpt-4", "User")
[System.Environment]::SetEnvironmentVariable("OPENAI_API_KEY", "sk-your-key", "User")
```

### Windows (System-Wide)

1. Press `Win + R`, type `sysdm.cpl`, press Enter
2. Go to "Advanced" tab → "Environment Variables"
3. Click "New" under "User variables" or "System variables"
4. Add:
   - Variable: `LLM_PROVIDER`, Value: `openai`
   - Variable: `LLM_MODEL`, Value: `gpt-4`
   - Variable: `OPENAI_API_KEY`, Value: `sk-your-key`

### Linux/Mac (Permanent)

Add to your `~/.bashrc` or `~/.zshrc`:
```bash
export LLM_PROVIDER=openai
export LLM_MODEL=gpt-4
export OPENAI_API_KEY=sk-your-key-here
```

Then reload:
```bash
source ~/.bashrc  # or source ~/.zshrc
```

---

## Testing Your Setup

1. **Check configuration:**
   ```bash
   python check_llm_config.py
   ```

2. **Test the system:**
   - Upload a video through the frontend
   - Request feedback
   - Check the console output - you should see:
     ```
     📝 Using LLM Provider: OPENAI, Model: gpt-4
     ```
   - The feedback should be more natural and conversational than the placeholder

---

## Troubleshooting

### "OPENAI_API_KEY environment variable not set"
- Make sure you set the environment variable in the same terminal where you're running the server
- Restart your terminal/server after setting variables
- Check with: `echo $OPENAI_API_KEY` (Linux/Mac) or `echo %OPENAI_API_KEY%` (Windows CMD)

### "OpenAI library not installed"
- Run: `pip install openai`

### "API error: Invalid API key"
- Check that your API key is correct
- Make sure there are no extra spaces or quotes around the key
- Verify your API key is active at https://platform.openai.com/api-keys

### Still using placeholder?
- Make sure you restarted the server after setting environment variables
- Check that `LLM_PROVIDER` is set correctly (lowercase: "openai", "anthropic", "ollama")
- Run `python check_llm_config.py` to verify

---

## Cost Considerations

- **OpenAI GPT-4**: ~$0.03 per request (varies by model and tokens)
- **OpenAI GPT-3.5-turbo**: ~$0.002 per request (much cheaper)
- **Anthropic Claude**: Similar pricing to GPT-4
- **Ollama**: Free (runs locally, no API costs)

To use a cheaper OpenAI model:
```bash
export LLM_MODEL=gpt-3.5-turbo
```









