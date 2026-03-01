#!/usr/bin/env python3
"""
Helper script to read .npy files and convert to JSON
"""
import sys
import os
import json

try:
    import numpy as np
except ImportError:
    print(json.dumps({'error': 'numpy is not installed. Please install it with: pip install numpy'}))
    sys.exit(1)

if len(sys.argv) < 2:
    print(json.dumps({'error': 'No file path provided'}))
    sys.exit(1)

npy_path = sys.argv[1]

if not os.path.exists(npy_path):
    print(json.dumps({'error': f'File not found: {npy_path}'}))
    sys.exit(1)

try:
    data = np.load(npy_path)
    result = data.tolist()

    json_output = json.dumps(result, allow_nan=False)
    print(json_output)
    sys.exit(0)
except Exception as e:
    error_msg = str(e)
    print(json.dumps({'error': f'Failed to read .npy file: {error_msg}'}))
    sys.exit(1)
