// Funciones globales del sistema

// Confirmación antes de eliminar
function confirmDelete(message = '¿Estás seguro de eliminar este registro?') {
    return confirm(message);
}

// Formatear fechas
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'long'
    };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}

// Formatear moneda
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(amount);
}

// Validar formularios
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('[required]');
    
    for (let input of inputs) {
        if (!input.value.trim()) {
            input.focus();
            input.style.borderColor = '#f56565';
            
            setTimeout(() => {
                input.style.borderColor = '';
            }, 2000);
            
            return false;
        }
    }
    
    return true;
}

// Mostrar/ocultar contraseña
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Cargar datos con AJAX
async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, options);
        if (!response.ok) throw new Error('Error en la petición');
        return await response.json();
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al cargar datos', 'error');
        return null;
    }
}

// Mostrar notificación
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification alert-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <p>${message}</p>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Calcular horas trabajadas
function calculateWorkHours(startTime, endTime, breakMinutes = 60) {
    if (!startTime || !endTime) return 0;
    
    const start = new Date(`2000-01-01T${startTime}`);
    const end = new Date(`2000-01-01T${endTime}`);
    
    let diffMs = end - start;
    let diffHours = diffMs / (1000 * 60 * 60);
    
    // Restar tiempo de descanso
    diffHours -= breakMinutes / 60;
    
    return diffHours > 0 ? diffHours.toFixed(2) : 0;
}

// Filtrar tabla
function filterTable(tableId, columnIndex, searchTerm) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const cell = rows[i].getElementsByTagName('td')[columnIndex];
        if (cell) {
            const text = cell.textContent || cell.innerText;
            rows[i].style.display = text.toLowerCase().indexOf(searchTerm.toLowerCase()) > -1 ? '' : 'none';
        }
    }
}

// Exportar tabla a CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    for (let row of rows) {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        
        for (let col of cols) {
            rowData.push(`"${col.innerText.replace(/"/g, '""')}"`);
        }
        
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }
}

// Cargar datos del empleado por ID
async function loadEmployeeData(employeeId) {
    const data = await fetchData(`../api/get_employee.php?id=${employeeId}`);
    
    if (data) {
        document.getElementById('employee-name').textContent = 
            `${data.nombre} ${data.apellido_paterno} ${data.apellido_materno}`;
        // ... cargar más datos
    }
}

// Inicializar componentes cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        el.setAttribute('data-tooltip', el.getAttribute('title'));
        el.removeAttribute('title');
    });
    
    // Inicializar filtros de búsqueda
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const tableId = this.getAttribute('data-table');
            const column = this.getAttribute('data-column');
            filterTable(tableId, column, this.value);
        });
    });
    
    // Inicializar select2 personalizado
    const selects = document.querySelectorAll('select[multiple]');
    selects.forEach(select => {
        select.style.minHeight = '100px';
    });
});