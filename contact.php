<?php
// Start session
session_start();

// Include configuration files
require_once 'config/database.php';
require_once 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize database connection
$database = new Database();
$db = $database->connect();

// Initialize variables
$name = $email = $subject = $message = "";
$name_err = $email_err = $subject_err = $message_err = "";
$success_msg = $error_msg = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $subject = trim($_POST["subject"] ?? '');
    $message = trim($_POST["message"] ?? '');
    
    // Validation
    $valid = true;
    
    if (empty($name)) {
        $name_err = "Please enter your name.";
        $valid = false;
    } elseif (strlen($name) > 100) {
        $name_err = "Name must be less than 100 characters.";
        $valid = false;
    }
    
    if (empty($email)) {
        $email_err = "Please enter your email address.";
        $valid = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
        $valid = false;
    } elseif (strlen($email) > 100) {
        $email_err = "Email must be less than 100 characters.";
        $valid = false;
    }
    
    if (empty($subject)) {
        $subject_err = "Please enter a subject.";
        $valid = false;
    } elseif (strlen($subject) > 200) {
        $subject_err = "Subject must be less than 200 characters.";
        $valid = false;
    }
    
    if (empty($message)) {
        $message_err = "Please enter your message.";
        $valid = false;
    } elseif (strlen($message) < 10) {
        $message_err = "Message must be at least 10 characters.";
        $valid = false;
    }
    
    // If validation passes
    if ($valid) {
        try {
            // Save to database
            $query = "INSERT INTO contacts (name, email, subject, message, created_at) 
                      VALUES (:name, :email, :subject, :message, NOW())";
            
            $stmt = $db->prepare($query);
            
            // Bind parameters
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":subject", $subject);
            $stmt->bindParam(":message", $message);
            
            // Execute query
            if ($stmt->execute()) {
                // Send email to admin using PHPMailer
                $mail_sent = sendContactEmail($name, $email, $subject, $message);
                
                // Send confirmation email to user
                $confirmation_sent = sendConfirmationEmail($name, $email);
                
                if ($mail_sent && $confirmation_sent) {
                    $success_msg = "Thank you! Your message has been sent successfully. We've sent a confirmation email to your inbox and we'll get back to you within 24 hours.";
                    
                    // Clear form fields
                    $name = $email = $subject = $message = "";
                } elseif ($mail_sent) {
                    $success_msg = "Thank you! Your message has been sent successfully. We'll get back to you within 24 hours.";
                    
                    // Clear form fields
                    $name = $email = $subject = $message = "";
                } else {
                    $error_msg = "Your message was saved but there was an issue sending the email notification. We'll still get back to you.";
                }
            } else {
                $error_msg = "Sorry, there was an error saving your message. Please try again.";
            }
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error_msg = "A database error occurred. Please try again later.";
        }
    } else {
        $error_msg = "Please fix the errors in the form.";
    }
}

// Function to send email to admin
function sendContactEmail($name, $email, $subject, $message) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings (UPDATE THESE WITH YOUR ACTUAL EMAIL CONFIG)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';                    // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'dimejirotimi5@gmail.com';            // Your email
        $mail->Password   = 'llaevscwtmpxroxr';               // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        

        // Recipients
        $mail->setFrom('noreply@coverall.com', 'Cover All Website');
        $mail->addAddress('dimejirotimi5@gmail.com', 'Cover All Team');  // Company email
        $mail->addReplyTo($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Contact Form Message: ' . $subject;
        $mail->Body    = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #2E8B57; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; border: 1px solid #ddd; }
                    .field { margin-bottom: 15px; }
                    .label { font-weight: bold; color: #2E8B57; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Contact Form Submission</h2>
                    </div>
                    <div class='content'>
                        <div class='field'>
                            <span class='label'>From:</span> {$name}
                        </div>
                        <div class='field'>
                            <span class='label'>Email:</span> {$email}
                        </div>
                        <div class='field'>
                            <span class='label'>Subject:</span> {$subject}
                        </div>
                        <div class='field'>
                            <span class='label'>Message:</span><br>
                            " . nl2br(htmlspecialchars($message)) . "
                        </div>
                        <div class='field'>
                            <span class='label'>Submitted:</span> " . date('F j, Y, g:i a') . "
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "New Contact Form Submission\n\nFrom: {$name}\nEmail: {$email}\nSubject: {$subject}\nMessage: {$message}\n\nSubmitted: " . date('F j, Y, g:i a');
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to send confirmation email to user
function sendConfirmationEmail($name, $email) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings (UPDATE THESE WITH YOUR ACTUAL EMAIL CONFIG)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';                    // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'dimejirotimi5@gmail.com';            // Your email
        $mail->Password   = 'llaevscwtmpxroxr';               // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        

        // Recipients
        $mail->setFrom('noreply@coverall.com', 'Cover All Team');
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'We Received Your Message - Cover All';
        $mail->Body    = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #2E8B57; color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 8px 8px; background-color: #f9f9f9; }
                    .message-box { background: white; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #2E8B57; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; }
                    .btn-primary { background-color: #2E8B57; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Thank You for Contacting Us</h1>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>{$name}</strong>,</p>
                        
                        <div class='message-box'>
                            <p>We've successfully received your message and our team will review it shortly.</p>
                            <p>We aim to respond to all inquiries within <strong>24 hours</strong> during business days.</p>
                        </div>
                        
                        <p><strong>What happens next?</strong></p>
                        <ul>
                            <li>Our support team will review your message</li>
                            <li>You'll receive a personalized response from our team</li>
                            <li>We'll work with you to address your inquiry</li>
                        </ul>
                        
                        <p>In the meantime, you can:</p>
                        <ul>
                            <li>Visit our <a href='#'>FAQ page</a> for quick answers</li>
                            <li>Check out our <a href='#'>Services page</a> to learn more</li>
                            <li>Follow us on social media for updates</li>
                        </ul>
                        
                        <p>If you have any urgent matters, feel free to call us directly at <strong>+1 (555) 123-4567</strong>.</p>
                        
                        <p>Best regards,<br>
                        <strong>The Cover All Team</strong></p>
                    </div>
                    <div class='footer'>
                        <p>© " . date('Y') . " Cover All. All rights reserved.</p>
                        <p>123 Business Avenue, Suite 100, New York, NY 10001</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Thank You for Contacting Cover All\n\nDear {$name},\n\nWe've successfully received your message and our team will review it shortly.\nWe aim to respond to all inquiries within 24 hours during business days.\n\nBest regards,\nThe Cover All Team\n\n© " . date('Y') . " Cover All. All rights reserved.\n123 Business Avenue, Suite 100, New York, NY 10001";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Confirmation email error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Cover All - Professional Solutions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2E8B57',
                        'primary-dark': '#1E6B47',
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --primary: #2E8B57;
            --primary-dark: #1E6B47;
            --scrollbar-width: 8px;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: var(--scrollbar-width);
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        /* Thin Mobile Scrollbar */
        @media (max-width: 768px) {
            ::-webkit-scrollbar {
                width: 4px;
            }
        }
        
        /* Navigation Styles */
        .nav-item {
            padding: 8px 0;
        }

        .nav-text {
            font-size: 16px;
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .nav-item:hover .nav-text {
            color: #2E8B57;
        }

        .nav-item.active .nav-text {
            color: #2E8B57;
            font-weight: 600;
        }

        .nav-line {
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(to right, #2E8B57, #1E6B47);
            border-radius: 2px;
            transform: translateX(-50%);
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
        }

        .nav-item:hover .nav-line {
            width: 100%;
            opacity: 1;
        }

        .nav-item.active .nav-line {
            width: 100%;
            opacity: 1;
            background: linear-gradient(to right, #2E8B57, #1E6B47);
        }
        
        /* Mobile Menu Overlay */
        .mobile-menu-open {
            overflow: hidden;
        }
        
        .mobile-menu-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        /* Progress Indicator */
        .progress-indicator {
            position: fixed;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 150px;
            background: #e5e7eb;
            border-radius: 2px;
            z-index: 100;
            display: none;
        }
        
        @media (min-width: 768px) {
            .progress-indicator {
                display: block;
            }
        }
        
        .progress-bar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 0;
            background: var(--primary);
            border-radius: 2px;
            transition: height 0.3s ease;
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }
        
        .check-icon {
            width: 80px;
            height: 80px;
            position: relative;
            border-radius: 50%;
            box-sizing: content-box;
            border: 4px solid #2E8B57;
        }
        
        .check-icon::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 60px;
            border-right: 5px solid #2E8B57;
            border-bottom: 5px solid #2E8B57;
            transform: rotate(45deg);
            left: 22px;
            top: 3px;
        }
        
        .input-focus-effect:focus {
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        }
        
        .contact-icon-hover:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Progress Indicator -->
    <div class="progress-indicator">
        <div class="progress-bar" id="progressBar"></div>
    </div>

    <!-- Navigation -->
    <nav class="fixed w-full bg-white z-50 shadow-sm">
        <div class="container mx-auto px-6">
            <div class="flex items-center justify-between py-4">
                <a href="index.html" class="flex items-center space-x-3 group">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary to-primary-dark rounded-xl flex items-center justify-center shadow-md">
                        <span class="text-white font-bold text-2xl">C</span>
                    </div>
                    <div>
                        <span class="text-2xl font-bold text-gray-900 tracking-tight">Cover All</span>
                        <span class="text-xs text-gray-500 block -mt-1">All-in-One Solutions</span>
                    </div>
                </a>
                
                <div class="hidden lg:flex items-center space-x-12">
                    <a href="index.html" class="nav-item relative">
                        <span class="nav-text text-gray-600">Home</span>
                        <span class="nav-line"></span>
                    </a>
                    <a href="about.html" class="nav-item relative">
                        <span class="nav-text text-gray-600">About</span>
                        <span class="nav-line"></span>
                    </a>
                    <a href="services.html" class="nav-item relative">
                        <span class="nav-text text-gray-600">Services</span>
                        <span class="nav-line"></span>
                    </a>
                    <a href="contact.php" class="nav-item relative active">
                        <span class="nav-text text-primary font-medium">Contact</span>
                        <span class="nav-line active"></span>
                    </a>

                </div>
                
                <button id="mobile-menu-button" class="lg:hidden text-gray-600 hover:text-primary transition-colors">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="fixed inset-0 z-40 hidden lg:hidden mobile-menu-overlay">
        <div class="absolute right-0 top-0 h-full w-4/5 max-w-sm bg-white shadow-xl transform transition-transform duration-300 translate-x-full">
            <div class="h-full overflow-y-auto">
                <!-- Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <a href="index.html" class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                                <span class="text-white font-bold text-xl">C</span>
                            </div>
                            <div>
                                <span class="text-xl font-bold text-gray-900">Cover All</span>
                                <span class="text-xs text-gray-600 block">All-in-One Solutions</span>
                            </div>
                        </a>
                        <button id="close-mobile-menu" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Navigation Links -->
                <div class="p-6">
                    <div class="space-y-2">
                        <a href="index.html" class="flex items-center justify-between p-4 rounded-xl hover:bg-gray-50 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-home text-gray-600"></i>
                                </div>
                                <span class="text-gray-700 font-medium">Home</span>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                        </a>
                        
                        <a href="about.html" class="flex items-center justify-between p-4 rounded-xl hover:bg-gray-50 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-info-circle text-gray-600"></i>
                                </div>
                                <span class="text-gray-700 font-medium">About</span>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                        </a>
                        
                        <a href="services.html" class="flex items-center justify-between p-4 rounded-xl hover:bg-gray-50 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-concierge-bell text-gray-600"></i>
                                </div>
                                <span class="text-gray-700 font-medium">Services</span>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 text-sm"></i>
                        </a>
                        
                        <a href="contact.php" class="flex items-center justify-between p-4 rounded-xl bg-primary/5 border-l-4 border-primary transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-phone-alt text-primary"></i>
                                </div>
                                <span class="text-primary font-semibold">Contact</span>
                            </div>
                            <i class="fas fa-chevron-right text-primary text-sm"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Stats Section -->
                <div class="p-6 border-t border-gray-100">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Why Choose Us</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-gray-50 rounded-xl">
                            <div class="text-2xl font-bold text-primary mb-1">15K+</div>
                            <div class="text-xs text-gray-600">Happy Clients</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-xl">
                            <div class="text-2xl font-bold text-primary mb-1">98%</div>
                            <div class="text-xs text-gray-600">Satisfaction</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-xl col-span-2">
                            <div class="text-2xl font-bold text-primary mb-1">24/7</div>
                            <div class="text-xs text-gray-600">Support Available</div>
                        </div>
                    </div>
                </div>
                
                <!-- CTA Button -->
                <div class="p-6 border-t border-gray-100">
                    <a href="services.html" 
                       class="w-full bg-gradient-to-r from-primary to-primary-dark text-white px-6 py-4 rounded-xl hover:shadow-lg transition-all duration-300 font-semibold flex items-center justify-center space-x-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Explore Now</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
<button id="backToTop" class="fixed bottom-8 right-8 z-40 bg-primary hover:bg-primary-dark text-white p-3 rounded-full shadow-lg transition-all duration-300 opacity-0 transform translate-y-10" style="box-shadow: 0 4px 20px rgba(46, 139, 87, 0.3);">
    <i class="fas fa-chevron-up text-xl"></i>
</button>

<script>
// Back to Top Functionality
document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.getElementById('backToTop');
    if (backToTopButton) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('show');
                backToTopButton.style.opacity = '1';
                backToTopButton.style.transform = 'translateY(0)';
            } else {
                backToTopButton.classList.remove('show');
                backToTopButton.style.opacity = '0';
                backToTopButton.style.transform = 'translateY(10px)';
            }
        });
        
        backToTopButton.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
</script>

<style>
#backToTop.show { opacity: 1 !important; transform: translateY(0) !important; }
#backToTop:hover { transform: translateY(-3px) !important; box-shadow: 0 6px 25px rgba(46, 139, 87, 0.4) !important; }
@media (max-width: 768px) { #backToTop { bottom: 6rem; right: 1.5rem; padding: 0.75rem; } }
</style>

    <!-- Hero Section with Better Image -->

<section class="pt-32 pb-20 relative overflow-hidden bg-cover bg-center" 
         style="background-image: url('https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');">

    <!-- Gradient Overlay Layer -->
    <div class="absolute inset-0 bg-gradient-to-r from-gray-900/80 via-gray-800/70 to-emerald-900/60"></div>

    
    <!-- Content Layer (Highest z-index) -->
    <div class="relative z-20">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mx-auto text-center">
                <span class="inline-block bg-white/20 text-white px-4 py-2 rounded-full text-sm font-medium mb-6">
                    <i class="fas fa-comments mr-2"></i> WE'RE HERE TO HELP
                </span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6">
                    Let's <span class="gradient-text">Connect</span>
                </h1>
                <p class="text-xl text-gray-100 mb-10 max-w-2xl mx-auto">
                    Your success is our priority. Reach out to our expert team for personalized solutions and dedicated support.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center relative z-30">
                    <a href="#contact-form" class="bg-gradient-to-r from-green-600 to-blue-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-xl transition-all duration-300 hover:-translate-y-1 flex items-center justify-center gap-2 relative z-40">
                        <i class="fas fa-paper-plane mr-2"></i>Send a Message
                    </a>
                    <a href="tel:+2349039178001" class="border-2 border-white/30 text-white px-6 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all duration-300 flex items-center justify-center gap-2 relative z-40">
                        <i class="fas fa-phone mr-2"></i>Call Now
                    </a>
                </div>
            </div>

            <style>        .gradient-text {
            background: linear-gradient(45deg, #2E8B57, #3B82F6, #F59E0B);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }</style>
            
            <!-- Stats Cards -->
            <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-6 max-w-3xl mx-auto relative z-30">
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 hover:bg-white/15 transition-all duration-300 hover:-translate-y-1 relative z-40">
                    <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-clock text-white text-2xl"></i>
                    </div>
                    <h3 class="text-white font-semibold mb-2">24/7 Response</h3>
                    <p class="text-gray-200 text-sm">We respond within hours</p>
                </div>
                
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 hover:bg-white/15 transition-all duration-300 hover:-translate-y-1 relative z-40">
                    <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-headset text-white text-2xl"></i>
                    </div>
                    <h3 class="text-white font-semibold mb-2">Expert Support</h3>
                    <p class="text-gray-200 text-sm">Dedicated team ready to help</p>
                </div>
                
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 hover:bg-white/15 transition-all duration-300 hover:-translate-y-1 relative z-40">
                    <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mb-4 mx-auto">
                        <i class="fas fa-shield-alt text-white text-2xl"></i>
                    </div>
                    <h3 class="text-white font-semibold mb-2">Secure & Private</h3>
                    <p class="text-gray-200 text-sm">Your data is protected</p>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- Success Modal -->
    <?php if (!empty($success_msg)): ?>
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-2xl p-8 max-w-md mx-4 animate-slide-up">
            <div class="success-checkmark mb-6">
                <div class="check-icon"></div>
            </div>
            <h3 class="text-2xl font-bold text-center text-gray-800 mb-4">Message Sent!</h3>
            <p class="text-gray-600 text-center mb-6"><?php echo $success_msg; ?></p>
            <div class="text-center">
                <button onclick="closeSuccessModal()" class="bg-primary text-white px-6 py-3 rounded-lg hover:bg-primary-dark transition-colors">
                    Continue
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
<!-- Contact Information -->
<div class="lg:col-span-1">
    <div class="bg-white rounded-2xl shadow-xl p-6 lg:p-8 sticky top-8 lg:top-24">
        <h2 class="text-xl lg:text-2xl font-bold text-gray-800 mb-6 lg:mb-8">Contact Information</h2>
        
        <div class="space-y-6 lg:space-y-8">
            <!-- Office Location -->
            <div class="flex items-start contact-icon-hover">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-primary/10 rounded-xl flex items-center justify-center mr-3 lg:mr-4 flex-shrink-0">
                    <i class="fas fa-map-marker-alt text-primary text-base lg:text-lg"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 text-sm lg:text-base">Our Office</h4>
                    <p class="text-gray-600 text-sm lg:text-base mt-1">123 Business Avenue, Suite 100<br>FCT Abuja, Abj 10001</p>
                </div>
            </div>
            
            <!-- Phone Number -->
            <div class="flex items-start contact-icon-hover">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-primary/10 rounded-xl flex items-center justify-center mr-3 lg:mr-4 flex-shrink-0">
                    <i class="fas fa-phone text-primary text-base lg:text-lg"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 text-sm lg:text-base">Phone Number</h4>
                    <p class="text-gray-600 text-sm lg:text-base mt-1">+234 901-5806-777<br>+234 903-9178-001</p>
                </div>
            </div>
            
            <!-- Email Address -->
            <div class="flex items-start contact-icon-hover">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-primary/10 rounded-xl flex items-center justify-center mr-3 lg:mr-4 flex-shrink-0">
                    <i class="fas fa-envelope text-primary text-base lg:text-lg"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 text-sm lg:text-base">Email Address</h4>
                    <p class="text-gray-600 text-sm lg:text-base mt-1">info@coverall.com<br>support@coverall.com</p>
                </div>
            </div>
            
            <!-- Working Hours -->
            <div class="flex items-start contact-icon-hover">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-primary/10 rounded-xl flex items-center justify-center mr-3 lg:mr-4 flex-shrink-0">
                    <i class="fas fa-clock text-primary text-base lg:text-lg"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 text-sm lg:text-base">Working Hours</h4>
                    <p class="text-gray-600 text-sm lg:text-base mt-1">Monday - Friday: 9AM - 6PM<br>Saturday: 10AM - 4PM</p>
                </div>
            </div>
        </div>
        
        <!-- Emergency Support -->
        <div class="mt-8 lg:mt-12 pt-6 lg:pt-8 border-t border-gray-200">
            <h4 class="font-semibold text-gray-800 text-sm lg:text-base mb-3 lg:mb-4">Emergency Support</h4>
            <div class="flex items-center">
                <div class="w-10 h-10 lg:w-12 lg:h-12 bg-red-100 rounded-xl flex items-center justify-center mr-3 lg:mr-4 flex-shrink-0">
                    <i class="fas fa-headset text-red-600 text-base lg:text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-gray-600 text-sm lg:text-base">24/7 Available for Priority Services</p>
                    <p class="text-red-600 font-semibold text-sm lg:text-base mt-1">+234 903-9178-001</p>
                </div>
            </div>
        </div>
    </div>
</div>
            
            <!-- Contact Form -->
            <div class="lg:col-span-2" id="contact-form">
                <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12">
                    <h2 class="text-3xl font-bold text-gray-800 mb-6">Send us a Message</h2>
                    <p class="text-gray-600 mb-8">Fill out the form below and we'll get back to you as soon as possible.</p>
                    
                    <?php if (!empty($error_msg)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8 animate-fade-in">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo $error_msg; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="contactForm" class="space-y-6" novalidate>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                <div class="relative">
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 input-focus-effect <?php echo (!empty($name_err)) ? 'border-red-500' : ''; ?>"
                                           placeholder="John Doe"
                                           required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                </div>
                                <?php if (!empty($name_err)): ?>
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php echo $name_err; ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <div class="relative">
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 input-focus-effect <?php echo (!empty($email_err)) ? 'border-red-500' : ''; ?>"
                                           placeholder="john@example.com"
                                           required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                </div>
                                <?php if (!empty($email_err)): ?>
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php echo $email_err; ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                            <div class="relative">
                                <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($subject); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 input-focus-effect <?php echo (!empty($subject_err)) ? 'border-red-500' : ''; ?>"
                                       placeholder="How can we help you?"
                                       required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i class="fas fa-tag text-gray-400"></i>
                                </div>
                            </div>
                            <?php if (!empty($subject_err)): ?>
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <?php echo $subject_err; ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                            <div class="relative">
                                <textarea id="message" name="message" rows="6"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 input-focus-effect <?php echo (!empty($message_err)) ? 'border-red-500' : ''; ?>"
                                          placeholder="Please provide details about your inquiry..."
                                          required><?php echo htmlspecialchars($message); ?></textarea>
                                <div class="absolute top-3 right-3 pr-3 flex items-start pointer-events-none">
                                    <i class="fas fa-comment text-gray-400"></i>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <?php if (!empty($message_err)): ?>
                                <p class="text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php echo $message_err; ?>
                                </p>
                                <?php endif; ?>
                                <span id="charCount" class="text-sm text-gray-500">0/1000</span>
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" id="submitBtn" class="w-full bg-primary text-white px-6 py-4 rounded-xl font-semibold hover:bg-primary-dark transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center group">
                                <i class="fas fa-paper-plane mr-3 group-hover:rotate-12 transition-transform"></i>
                                Send Message
                                <i class="fas fa-arrow-right ml-3 group-hover:translate-x-2 transition-transform"></i>
                            </button>
                            <p class="text-center text-gray-500 text-sm mt-4">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Your information is secure and will not be shared
                            </p>
                        </div>
                    </form>
                </div>
                
                <!-- Additional Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-2xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-lightbulb text-blue-600"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800">Before You Contact</h4>
                        </div>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                                Check our FAQ page for quick answers
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                                Have your account information ready
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                                Provide specific details for faster resolution
                            </li>
                        </ul>
                    </div>
                    
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-rocket text-green-600"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800">Response Time</h4>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-500">General Inquiries</p>
                                <p class="font-semibold text-gray-800">Within 24 hours</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Emergency Support</p>
                                <p class="font-semibold text-gray-800">Immediate (24/7)</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Service Requests</p>
                                <p class="font-semibold text-gray-800">Within 2 business hours</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Section -->
        <div class="mt-16">
            <div class="rounded-2xl shadow-xl overflow-hidden">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3024.177858804427!2d-74.00705308459418!3d40.713129179331805!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c25a3165da7f1f%3A0x6eb93c7c623c1b32!2s123%20Business%20Ave%2C%20New%20York%2C%20NY%2010001%2C%20USA!5e0!3m2!1sen!2s!4v1617125690635!5m2!1sen!2s" 
                        width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
            </div>
        </div>
    </div>

     <!-- Footer -->
    <footer class="bg-black text-white pt-12 pb-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center mr-3">
                            <span class="text-white font-bold text-xl">C</span>
                        </div>
                        <div>
                            <span class="text-xl font-bold">Cover All</span>
                            <span class="text-xs text-primary block">All-in-One Solutions</span>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm mb-4">
                        Your comprehensive solution platform for modern living and business operations.
                    </p>
                    <div class="flex gap-2">
                        <a href="#" class="w-8 h-8 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-facebook-f text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-twitter text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-linkedin-in text-sm"></i>
                        </a>
                        <a href="#" class="w-8 h-8 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-instagram text-sm"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Company</h4>
                    <ul class="space-y-2">
                        <li><a href="about.html" class="text-gray-400 hover:text-white transition-colors text-sm">About Us</a></li>
                        <li><a href="contact.php#careers" class="text-gray-400 hover:text-white transition-colors text-sm">Careers</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">Press</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">Blog</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Services</h4>
                    <ul class="space-y-2">
                        <li><a href="services.html#transportation" class="text-gray-400 hover:text-white transition-colors text-sm">Transportation</a></li>
                        <li><a href="services.html#waste" class="text-gray-400 hover:text-white transition-colors text-sm">Waste Management</a></li>
                        <li><a href="services.html#logistics" class="text-gray-400 hover:text-white transition-colors text-sm">Logistics</a></li>
                        <li><a href="services.html#maintenance" class="text-gray-400 hover:text-white transition-colors text-sm">Maintenance</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold mb-4">Stay Updated</h4>
                    <p class="text-gray-400 text-sm mb-4">
                        Subscribe to our newsletter for the latest updates.
                    </p>
                    <form class="flex">
                        <input type="email" placeholder="Enter your email" required
                               class="flex-grow px-4 py-2 rounded-l-lg text-gray-900 focus:outline-none text-sm">
                        <button type="submit" class="bg-primary px-4 py-2 rounded-r-lg hover:bg-primary-dark transition-colors">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-6">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="mb-4 md:mb-0">
                        <p class="text-gray-400 text-sm">
                            &copy; 2025 Cover All. All rights reserved. 
<a href="privacy-policy.html" class="text-primary transition-colors hover:text-white">Privacy Policy</a> | 
<a href="terms-of-service.html" class="text-primary transition-colors hover:text-white">Terms of Service</a>
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <a href="#" class="flex items-center gap-2 bg-gray-800 px-3 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm">
                            <i class="fab fa-apple"></i>
                            <div class="text-xs">
                                <div>Download on the</div>
                                <div class="font-bold">App Store</div>
                            </div>
                        </a>
                        <a href="#" class="flex items-center gap-2 bg-gray-800 px-3 py-2 rounded-lg hover:bg-gray-700 transition-colors text-sm">
                            <i class="fab fa-google-play"></i>
                            <div class="text-xs">
                                <div>Get it on</div>
                                <div class="font-bold">Google Play</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <!-- JavaScript -->
    <script>
        // Mobile Menu Functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const closeMobileMenu = document.getElementById('close-mobile-menu');
        const mobileMenuPanel = mobileMenu.querySelector('.absolute');

        // Open mobile menu
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.remove('hidden');
            setTimeout(() => {
                mobileMenuPanel.classList.remove('translate-x-full');
            }, 10);
            document.body.classList.add('mobile-menu-open');
        });

        // Close mobile menu
        function closeMenu() {
            mobileMenuPanel.classList.add('translate-x-full');
            setTimeout(() => {
                mobileMenu.classList.add('hidden');
                document.body.classList.remove('mobile-menu-open');
            }, 300);
        }

        closeMobileMenu.addEventListener('click', closeMenu);
        
        // Close menu when clicking outside
        mobileMenu.addEventListener('click', (e) => {
            if (e.target === mobileMenu) {
                closeMenu();
            }
        });

        // Scroll Progress Indicator
        function updateProgressBar() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            const progressBar = document.getElementById('progressBar');
            if (progressBar) {
                progressBar.style.height = scrolled + '%';
            }
        }

        window.addEventListener('scroll', updateProgressBar);
        window.addEventListener('load', updateProgressBar);

        // Character counter for message
        const messageInput = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        
        if (messageInput && charCount) {
            messageInput.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = `${length}/1000`;
                
                if (length > 1000) {
                    charCount.classList.add('text-red-600');
                } else if (length > 800) {
                    charCount.classList.add('text-yellow-600');
                } else {
                    charCount.classList.remove('text-red-600', 'text-yellow-600');
                }
            });
        }

        // Form validation
        const form = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form && submitBtn) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Validate name
                const nameInput = document.getElementById('name');
                if (!nameInput.value.trim()) {
                    showError(nameInput, 'Please enter your name');
                    isValid = false;
                } else {
                    removeError(nameInput);
                }
                
                // Validate email
                const emailInput = document.getElementById('email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value.trim())) {
                    showError(emailInput, 'Please enter a valid email address');
                    isValid = false;
                } else {
                    removeError(emailInput);
                }
                
                // Validate subject
                const subjectInput = document.getElementById('subject');
                if (!subjectInput.value.trim()) {
                    showError(subjectInput, 'Please enter a subject');
                    isValid = false;
                } else {
                    removeError(subjectInput);
                }
                
                // Validate message
                const messageInput = document.getElementById('message');
                if (!messageInput.value.trim()) {
                    showError(messageInput, 'Please enter your message');
                    isValid = false;
                } else if (messageInput.value.trim().length < 10) {
                    showError(messageInput, 'Message must be at least 10 characters');
                    isValid = false;
                } else {
                    removeError(messageInput);
                }
                
                if (!isValid) {
                    e.preventDefault();
                } else {
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i>Sending...';
                    submitBtn.disabled = true;
                }
            });
        }
        
        function showError(input, message) {
            if (!input) return;
            
            input.classList.add('border-red-500');
            
            let errorDiv = input.nextElementSibling;
            if (!errorDiv || !errorDiv.classList.contains('error-message')) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message mt-2 text-sm text-red-600 flex items-center';
                input.parentNode.appendChild(errorDiv);
            }
            
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
        }
        
        function removeError(input) {
            if (!input) return;
            
            input.classList.remove('border-red-500');
            
            const errorDiv = input.parentNode.querySelector('.error-message');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
        
        // Close success modal
        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Auto-close success modal after 5 seconds
        setTimeout(() => {
            closeSuccessModal();
        }, 5000);
        
        // Initialize character count
        if (messageInput && charCount && messageInput.value) {
            charCount.textContent = `${messageInput.value.length}/1000`;
        }
    </script>
</body>
</html>