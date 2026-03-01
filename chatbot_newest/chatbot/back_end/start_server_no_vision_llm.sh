#!/bin/bash
# Start server without Vision LLM
echo "============================================================"
echo "Starting Pickleball Chatbot Server (Vision LLM Disabled)"
echo "============================================================"
echo ""
echo "The server will start without Vision LLM model loading."
echo "This is faster and uses less memory."
echo ""
export ENABLE_VISION_LLM=false
python main.py















