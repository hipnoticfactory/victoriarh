<?php
// includes/footer.php
?>
    </main>
    
    <!-- Footer simple -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <img src="/sistema-empleados/assets/icono.png" alt="Logo">
                <span>Sistema de Control de Empleados</span>
            </div>
            <div class="footer-info">
                <p>© <?php echo date('Y'); ?> - Todos los derechos reservados</p>
                <p>Versión 1.0.0</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Scripts básicos
        document.addEventListener('DOMContentLoaded', function() {
            // Marcar enlace activo
            const currentPage = window.location.pathname;
            document.querySelectorAll('.nav-link').forEach(link => {
                if (link.href.includes(currentPage)) {
                    link.classList.add('active');
                }
            });
            
            // Actualizar fecha en tiempo real
            function updateDate() {
                const dateElement = document.querySelector('.current-date');
                if (dateElement) {
                    const now = new Date();
                    const options = { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    };
                    dateElement.textContent = now.toLocaleDateString('es-ES', options);
                }
            }
            
            // Llamar una vez
            updateDate();
        });
    </script>
</body>
</html>