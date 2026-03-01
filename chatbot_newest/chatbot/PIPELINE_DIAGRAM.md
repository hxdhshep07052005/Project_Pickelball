# Pickleball Training Chatbot System - Pipeline Diagram

## Mermaid Diagram

```mermaid
flowchart TD
    Start([User Uploads Video<br/>3-5 seconds, max 50MB]) --> Upload[Video Upload & Session Creation<br/>UUID Session ID]
    
    Upload --> Extract[Frame Extraction<br/>Sharpness + Motion + Pose Detection<br/>~10 keyframes]
    
    Extract --> Pose[Pose Estimation<br/>MediaPipe<br/>33 Body Landmarks]
    Extract --> VisionLLM[Vision LLM Analysis<br/>BLIP Model<br/>Visual Form Description]
    
    Pose --> Combine[Data Combination<br/>Merge Pose + Vision LLM Data]
    VisionLLM --> Combine
    
    Combine --> Phase[Phase Detection<br/>READY → BACKSWING → CONTACT → FOLLOW_THROUGH]
    
    Phase --> Rules[Rule-Based Evaluation<br/>Quantitative + Qualitative Analysis<br/>Issue Detection]
    
    Rules --> Feedback[Structured Feedback JSON<br/>Issue Codes, Severity, Tips]
    
    Feedback --> LLM[Text LLM Processing<br/>OpenAI/Anthropic/Ollama/Placeholder]
    
    LLM --> Output[Natural Language<br/>Coaching Feedback]
    
    Output --> Display([User Interface<br/>Feedback Display])
    
    style Start fill:#e1f5ff
    style Upload fill:#fff4e1
    style Extract fill:#ffe1f5
    style Pose fill:#e1ffe1
    style VisionLLM fill:#e1ffe1
    style Combine fill:#f5e1ff
    style Phase fill:#ffe1e1
    style Rules fill:#ffffe1
    style Feedback fill:#e1ffff
    style LLM fill:#f5f5e1
    style Output fill:#e1f5e1
    style Display fill:#e1f5ff
```

## Detailed Pipeline Flow

```mermaid
graph TB
    subgraph "Input Stage"
        A[User Uploads Video<br/>MP4/MOV/AVI/MKV/WebM<br/>3-5 seconds]
    end
    
    subgraph "Processing Stage 1: Frame Extraction"
        B[Extract Keyframes<br/>Sharpness Score<br/>Motion Score<br/>Pose Detection Score]
    end
    
    subgraph "Processing Stage 2: Analysis"
        C[MediaPipe Pose Estimation<br/>33 Body Landmarks<br/>Coordinates & Visibility]
        D[Vision LLM BLIP<br/>Stance, Balance, Rotation<br/>Arm Extension, Hand Position]
    end
    
    subgraph "Processing Stage 3: Data Fusion"
        E[Combine Data<br/>Pose Landmarks +<br/>Vision LLM Insights]
    end
    
    subgraph "Processing Stage 4: Phase Detection"
        F[Detect Swing Phases<br/>READY<br/>BACKSWING<br/>CONTACT<br/>FOLLOW_THROUGH]
    end
    
    subgraph "Processing Stage 5: Evaluation"
        G[Rule-Based Evaluation<br/>Phase Completeness<br/>Joint Angles<br/>Motion Quality<br/>Visual Form]
    end
    
    subgraph "Output Stage"
        H[Structured Feedback<br/>Issue Codes<br/>Severity Levels<br/>Coaching Tips]
        I[Text LLM Generation<br/>Natural Language<br/>Coaching Feedback]
        J[User Display<br/>Feedback Interface]
    end
    
    A --> B
    B --> C
    B --> D
    C --> E
    D --> E
    E --> F
    F --> G
    G --> H
    H --> I
    I --> J
    
    style A fill:#4a90e2,color:#fff
    style B fill:#7b68ee,color:#fff
    style C fill:#50c878,color:#fff
    style D fill:#50c878,color:#fff
    style E fill:#ff6b9d,color:#fff
    style F fill:#ffa500,color:#fff
    style G fill:#ffd700,color:#000
    style H fill:#87ceeb,color:#000
    style I fill:#98d8c8,color:#000
    style J fill:#4a90e2,color:#fff
```

## How to View These Diagrams

1. **GitHub/GitLab**: Mermaid diagrams render automatically in markdown files
2. **VS Code**: Install "Markdown Preview Mermaid Support" extension
3. **Online**: Copy the mermaid code to https://mermaid.live/
4. **Documentation Tools**: Many documentation platforms support Mermaid natively
















