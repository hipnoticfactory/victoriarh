Tenemos este sistema a medias:

htdocs/sistema-empleados/
├── index.php
├── login.php
├── logout.php
├── install.php (temporal)
├── .htaccess
├── assets/
│   ├── icono.png
│   └── logo.png
├── config/
│   └── database.php
├── css/
│   └── styles.css
├── js/
│   └── scripts.js
├── includes/
│   ├── auth_check.php
│   ├── header.php
│   ├── footer.php
│   ├── functions.php
│   └── nav.php
├── modules/
│   ├── dashboard/
│   │   └── index.php
│   ├── empleados/
│   │   ├── index.php
│   │   ├── agregar.php
│   │   ├── editar.php
│   │   ├── ver.php
│   │   ├── eliminar.php
│   │   └── buscar.php
│   ├── expedientes/
│   │   ├── index.php
│   │   ├── subir.php
│   │   ├── ver.php
│   │   └── eliminar.php
│   ├── asistencias/
│   │   ├── index.php
│   │   ├── registrar.php
│   │   ├── editar.php
│   │   ├── historial.php
│   │   └── marcar.php
│   ├── reportes/
│   │   ├── index.php
│   │   ├── diario.php
│   │   ├── quincenal.php
│   │   ├── mensual.php
│   │   └── exportar.php
│   └── usuarios/
│       ├── index.php
│       ├── perfil.php
│       └── cambiar_password.php
├── uploads/
│   ├── .htaccess
│   └── empleados/
├── api/
│   ├── get_empleados.php
│   ├── get_asistencias.php
│   └── get_reportes.php
└── temp/ (para archivos temporales)

Este sistema trabaja en Xampp en Linux. La interfaz esta muy bonita y no hay que modificar, el problema son los archivos, hay que arreglar.
