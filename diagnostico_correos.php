<?php
require_once 'config/config.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Correos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .diagnostic-card { 
            background: white; 
            border-radius: 8px; 
            padding: 20px; 
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .diagnostic-card.error {
            border-left-color: #dc3545;
        }
        .diagnostic-card.success {
            border-left-color: #28a745;
        }
        .diagnostic-card.warning {
            border-left-color: #ffc107;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .test-button {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container max-width-800">
        <h1 class="mb-4">
            <i class="fas fa-envelope me-2"></i> Diagnóstico de Sistema de Correos
        </h1>

        <!-- Configuración SMTP -->
        <div class="diagnostic-card">
            <h4><i class="fas fa-cog me-2"></i> Configuración SMTP</h4>
            <table class="table table-sm mb-0">
                <tr>
                    <td><strong>SMTP Host:</strong></td>
                    <td><code><?php echo SMTP_HOST; ?></code></td>
                </tr>
                <tr>
                    <td><strong>SMTP Port:</strong></td>
                    <td><code><?php echo SMTP_PORT; ?></code></td>
                </tr>
                <tr>
                    <td><strong>SMTP User:</strong></td>
                    <td><code><?php echo SMTP_USER; ?></code></td>
                </tr>
                <tr>
                    <td><strong>SMTP Pass:</strong></td>
                    <td><code>●●●●●●●●●●●●</code> (Configurada)</td>
                </tr>
            </table>
        </div>

        <!-- Verificación de funciones PHP -->
        <div class="diagnostic-card <?php echo function_exists('mail') ? 'success' : 'error'; ?>">
            <h4><i class="fas fa-check-circle me-2"></i> Función mail() de PHP</h4>
            <?php if (function_exists('mail')): ?>
                <p class="text-success mb-0">
                    <i class="fas fa-check me-1"></i> 
                    <strong>Disponible</strong> - PHP puede enviar correos
                </p>
            <?php else: ?>
                <p class="text-danger mb-0">
                    <i class="fas fa-times me-1"></i> 
                    <strong>No disponible</strong> - La función mail() no está habilitada
                </p>
            <?php endif; ?>
        </div>

        <!-- Verificación de directorios -->
        <div class="diagnostic-card <?php echo is_writable('logs/') ? 'success' : 'warning'; ?>">
            <h4><i class="fas fa-folder me-2"></i> Directorio de Logs</h4>
            <?php 
                $logs_exist = is_dir('logs/');
                $logs_writable = is_writable('logs/');
            ?>
            <?php if ($logs_exist && $logs_writable): ?>
                <p class="text-success mb-0">
                    <i class="fas fa-check me-1"></i>
                    <strong>Accesible</strong> - Los logs se guardaran en <code>logs/</code>
                </p>
            <?php elseif ($logs_exist): ?>
                <p class="text-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>Directorio existente pero sin permisos de escritura</strong>
                </p>
            <?php else: ?>
                <p class="text-danger mb-0">
                    <i class="fas fa-times me-1"></i>
                    <strong>Directorio no existe</strong> - Crear manualmente: <code>mkdir logs</code>
                </p>
            <?php endif; ?>
        </div>

        <!-- Test de envío -->
        <div class="diagnostic-card">
            <h4><i class="fas fa-envelope-circle-check me-2"></i> Prueba de Envío</h4>
            <p>Ingrese un correo para enviar una prueba:</p>
            <form id="testForm">
                <div class="input-group mb-2">
                    <input type="email" class="form-control" id="testEmail" placeholder="correo@ejemplo.com" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Enviar Prueba
                    </button>
                </div>
            </form>
            <div id="testResult"></div>
        </div>

        <!-- Logs de correos -->
        <div class="diagnostic-card">
            <h4><i class="fas fa-history me-2"></i> Logs de Correos Recientes</h4>
            <?php 
                $log_file = 'logs/correos_' . date('Y-m-d') . '.log';
                if (file_exists($log_file)):
                    $logs = file_get_contents($log_file);
                    $lines = array_filter(explode("\n", $logs));
                    $recent = array_slice($lines, -10);
            ?>
                <pre><?php echo implode("\n", $recent); ?></pre>
            <?php else: ?>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    No hay logs aún. Los logs aparecerán aquí después de enviar correos.
                </p>
            <?php endif; ?>
        </div>

        <!-- Solución de problemas -->
        <div class="diagnostic-card warning">
            <h4><i class="fas fa-lightbulb me-2"></i> Solución de Problemas</h4>
            <div class="accordion" id="solutionsAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#solution1">
                            Los correos no se envían pero dice que sí
                        </button>
                    </h2>
                    <div id="solution1" class="accordion-collapse collapse" data-bs-parent="#solutionsAccordion">
                        <div class="accordion-body">
                            <p><strong>Problema:</strong> XAMPP no tiene servidor de correos configurado.</p>
                            <p><strong>Solución:</strong></p>
                            <ol>
                                <li>Abre <code>php.ini</code> en XAMPP</li>
                                <li>Busca <code>[mail function]</code></li>
                                <li>Configura:
                                    <pre>SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = matienda33@gmail.com</pre>
                                </li>
                                <li>Reinicia Apache</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#solution2">
                            Gmail rechaza los correos
                        </button>
                    </h2>
                    <div id="solution2" class="accordion-collapse collapse" data-bs-parent="#solutionsAccordion">
                        <div class="accordion-body">
                            <p><strong>Problema:</strong> Gmail bloquea aplicaciones "inseguras" o requiere autenticación de 2 factores.</p>
                            <p><strong>Solución:</strong></p>
                            <ol>
                                <li>Ve a <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></li>
                                <li>Genera una contraseña de aplicación para "Correo" en "Windows"</li>
                                <li>Copia la contraseña generada (16 caracteres)</li>
                                <li>Actualiza <code>config.php</code> con la nueva contraseña</li>
                                <li>La contraseña actual en config.php: <code><?php echo substr(SMTP_PASS, 0, 4); ?>...<?php echo substr(SMTP_PASS, -4); ?></code></li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#solution3">
                            Alternativa: Configurar servidor de correos local
                        </button>
                    </h2>
                    <div id="solution3" class="accordion-collapse collapse" data-bs-parent="#solutionsAccordion">
                        <div class="accordion-body">
                            <p><strong>Opción:</strong> Usar MailHog o Mailtrap para desarrollo</p>
                            <p>
                                <strong>MailHog:</strong> Servidor de correos fake para testing<br>
                                Descarga: <a href="https://github.com/mailhog/MailHog/releases" target="_blank">github.com/mailhog/MailHog</a>
                            </p>
                            <p>
                                <strong>Mailtrap:</strong> Servicio en línea gratuito<br>
                                Web: <a href="https://mailtrap.io" target="_blank">mailtrap.io</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('testEmail').value;
            const resultDiv = document.getElementById('testResult');
            
            resultDiv.innerHTML = '<div class="alert alert-info">Enviando prueba...</div>';
            
            fetch('api/test_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Éxito:</strong> Correo enviado a ${email}<br>
                            <small>Revisa los logs: <code>logs/correos_${new Date().toISOString().split('T')[0]}.log</code></small>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Error:</strong> ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>Error:</strong> ${error.message}
                    </div>
                `;
            });
        });
    </script>
</body>
</html>
