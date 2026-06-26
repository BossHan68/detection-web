<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ | Fall Detection System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary-color: #4f46e5;
        --danger-color: #ef4444;
        --success-color: #10b981;
        --dark-bg: #1e293b;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 20px;
    }
    
    .login-container {
        max-width: 450px;
        width: 100%;
    }
    
    .login-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .login-header {
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        padding: 40px 30px;
        text-align: center;
        color: white;
    }
    
    .login-header i {
        font-size: 3.5rem;
        margin-bottom: 15px;
        animation: pulse 2s infinite;
    }
    
    .login-header h4 {
        font-weight: 700;
        margin: 0 0 8px 0;
        font-size: 1.8rem;
    }
    
    .login-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }
    
    .login-body {
        padding: 40px 35px;
    }
    
    .alert {
        border-radius: 12px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }
    
    .alert i {
        font-size: 1.3rem;
    }
    
    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: var(--danger-color);
    }
    
    .alert-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: var(--success-color);
    }
    
    .form-group {
        margin-bottom: 20px;
        position: relative;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        color: var(--dark-bg);
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .input-wrapper {
        position: relative;
    }
    
    .input-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.1rem;
    }
    
    .form-control {
        width: 100%;
        padding: 14px 18px 14px 50px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        background: white;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    
    .form-control::placeholder {
        color: #cbd5e1;
    }
    
    .btn-login {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, var(--primary-color), #7c3aed);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.05rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);
    }
    
    .btn-login:active {
        transform: translateY(0);
    }
    
    .divider {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 25px 0;
        color: #94a3b8;
        font-size: 0.9rem;
    }
    
    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .divider span {
        padding: 0 15px;
    }
    
    .register-link {
        text-align: center;
        margin-top: 25px;
        padding-top: 25px;
        border-top: 1px solid #e2e8f0;
    }
    
    .register-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .register-link a:hover {
        color: #7c3aed;
        gap: 12px;
    }
    
    .floating-shapes {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: -1;
        overflow: hidden;
        pointer-events: none;
    }
    
    .shape {
        position: absolute;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        animation: float 20s infinite;
    }
    
    .shape:nth-child(1) {
        width: 80px;
        height: 80px;
        left: 10%;
        animation-delay: 0s;
    }
    
    .shape:nth-child(2) {
        width: 120px;
        height: 120px;
        right: 15%;
        top: 20%;
        animation-delay: 2s;
    }
    
    .shape:nth-child(3) {
        width: 60px;
        height: 60px;
        left: 70%;
        top: 60%;
        animation-delay: 4s;
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes float {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        50% {
            transform: translateY(-20px) rotate(180deg);
        }
    }
    
    @media (max-width: 576px) {
        .login-body {
            padding: 30px 25px;
        }
        
        .login-header {
            padding: 35px 25px;
        }
        
        .login-header h4 {
            font-size: 1.5rem;
        }
    }
</style>
</head>

<body>

<!-- Floating Shapes -->
<div class="floating-shapes">
    <div class="shape"></div>
    <div class="shape"></div>
    <div class="shape"></div>
</div>

<div class="login-container">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h4>Fall Detection</h4>
            <p>ระบบตรวจจับการล้ม</p>
        </div>
        
        <!-- Body -->
        <div class="login-body">
            <h5 style="color: var(--dark-bg); margin-bottom: 8px; font-weight: 700;">
                ยินดีต้อนรับ
            </h5>
            <p style="color: #64748b; margin-bottom: 30px; font-size: 0.95rem;">
                กรุณาเข้าสู่ระบบเพื่อเข้าใช้งาน
            </p>
            
            <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span>ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง</span>
            </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['register'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ</span>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="login_action.php">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-id-card"></i> รหัสนักศึกษา
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input 
                            type="text" 
                            name="student_id" 
                            class="form-control" 
                            placeholder="กรอกรหัสนักศึกษา" 
                            required
                            autofocus
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> รหัสผ่าน
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-key input-icon"></i>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="กรอกรหัสผ่าน" 
                            required
                        >
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>เข้าสู่ระบบ</span>
                </button>
            </form>
            
            <div class="register-link">
                <p style="color: #64748b; margin-bottom: 10px; font-size: 0.9rem;">
                    ยังไม่มีบัญชี?
                </p>
                <a href="register.php">
                    <i class="fas fa-user-plus"></i>
                    <span>สมัครสมาชิก</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Footer Info -->
    <div style="text-align: center; margin-top: 20px; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
        <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">
            <i class="fas fa-shield-alt"></i> ระบบปลอดภัย & เข้ารหัสข้อมูล
        </p>
    </div>
</div>

</body>
</html>