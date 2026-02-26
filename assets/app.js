import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// Import jQuery (required for DataTables)
import $ from 'jquery';
window.$ = window.jQuery = $;

// Import DataTables library and default styling
import 'datatables.net-dt/css/dataTables.dataTables.min.css';
import 'datatables.net-dt';

// Import DataTables Buttons extension (for export/print)
import 'datatables.net-buttons-dt/css/buttons.dataTables.min.css';
import 'datatables.net-buttons-dt';

// Import DataTables Responsive extension
import 'datatables.net-responsive-dt/css/responsive.dataTables.min.css';
import 'datatables.net-responsive-dt';
