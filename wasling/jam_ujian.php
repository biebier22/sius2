<style>
.jam-ujian-container {
    padding: 0;
}

.jam-ujian-header {
    margin-bottom: 20px;
}

.jam-ujian-header h2 {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 600;
    color: #0f172a;
}

.jam-ujian-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.jam-card {
    background: white;
    border-radius: 16px;
    padding: 18px 20px;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(15,23,42,0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-left: 4px solid transparent;
    position: relative;
    overflow: hidden;
}

.jam-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15,23,42,0.12);
}

.jam-card.jam-1 {
    background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
    border-left-color: #0ea5e9;
}

.jam-card.jam-2 {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-left-color: #ef4444;
}

.jam-card.jam-3 {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border-left-color: #f59e0b;
}

.jam-card.istirahat {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left-color: #10b981;
}

.jam-card.jam-4 {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border-left-color: #10b981;
}

.jam-card.jam-5 {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left-color: #3b82f6;
}

.jam-card-content {
    display: flex;
    align-items: center;
    gap: 16px;
}

.jam-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.7);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.jam-1 .jam-icon {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: white;
}

.jam-2 .jam-icon {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.jam-3 .jam-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.istirahat .jam-icon {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.jam-4 .jam-icon {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.jam-5 .jam-icon {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.jam-info {
    flex: 1;
}

.jam-label {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.jam-waktu {
    font-size: 15px;
    color: #475569;
    margin: 0;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.jam-waktu::before {
    content: "🕐";
    font-size: 14px;
}

.jam-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 8px;
}

.jam-1 .jam-badge {
    background: rgba(14, 165, 233, 0.1);
    color: #0284c7;
}

.jam-2 .jam-badge {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

.jam-3 .jam-badge {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.istirahat .jam-badge {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.jam-4 .jam-badge {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.jam-5 .jam-badge {
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
}

@media (max-width: 480px) {
    .jam-card {
        padding: 16px 18px;
    }
    
    .jam-icon {
        width: 44px;
        height: 44px;
        font-size: 18px;
    }
    
    .jam-label {
        font-size: 13px;
    }
    
    .jam-waktu {
        font-size: 14px;
    }
}
</style>

<div class="jam-ujian-container">
    <div class="jam-ujian-header">
        <h2>⏱️ Jam Ujian</h2>
        <p>Jadwal waktu ujian hari ini</p>
    </div>

    <div class="jam-card jam-1">
        <div class="jam-card-content">
            <div class="jam-icon">1</div>
            <div class="jam-info">
                <p class="jam-label">
                    Jam 1
                    <span class="jam-badge">Pagi</span>
                </p>
                <p class="jam-waktu">07:00 - 08:30</p>
            </div>
        </div>
    </div>

    <div class="jam-card jam-2">
        <div class="jam-card-content">
            <div class="jam-icon">2</div>
            <div class="jam-info">
                <p class="jam-label">
                    Jam 2
                    <span class="jam-badge">Pagi</span>
                </p>
                <p class="jam-waktu">08:45 - 10:15</p>
            </div>
        </div>
    </div>

    <div class="jam-card jam-3">
        <div class="jam-card-content">
            <div class="jam-icon">3</div>
            <div class="jam-info">
                <p class="jam-label">
                    Jam 3
                    <span class="jam-badge">Siang</span>
                </p>
                <p class="jam-waktu">10:30 - 12:00</p>
            </div>
        </div>
    </div>

    <div class="jam-card istirahat">
        <div class="jam-card-content">
            <div class="jam-icon">☕</div>
            <div class="jam-info">
                <p class="jam-label">
                    Istirahat
                    <span class="jam-badge">Break</span>
                </p>
                <p class="jam-waktu">12:00 - 12:45</p>
            </div>
        </div>
    </div>

    <div class="jam-card jam-4">
        <div class="jam-card-content">
            <div class="jam-icon">4</div>
            <div class="jam-info">
                <p class="jam-label">
                    Jam 4
                    <span class="jam-badge">Siang</span>
                </p>
                <p class="jam-waktu">12:45 - 14:15</p>
            </div>
        </div>
    </div>

    <div class="jam-card jam-5">
        <div class="jam-card-content">
            <div class="jam-icon">5</div>
            <div class="jam-info">
                <p class="jam-label">
                    Jam 5
                    <span class="jam-badge">Sore</span>
                </p>
                <p class="jam-waktu">14:30 - 16:00</p>
            </div>
        </div>
    </div>
</div>
