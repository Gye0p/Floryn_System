import { Controller } from '@hotwired/stimulus';
import $ from 'jquery';
import 'datatables.net-dt';
import 'datatables.net-buttons-dt';
import 'datatables.net-responsive-dt';

/*
 * DataTable Controller with Buttons & Responsive
 * Automatically initializes DataTables on tables with data-controller="datatable"
 * 
 * Usage:
 * <table data-controller="datatable" class="display" style="width:100%">
 *   ...
 * </table>
 * 
 * Custom options can be passed via data attributes:
 * data-datatable-page-length-value="25"
 * data-datatable-searching-value="true"
 * data-datatable-ordering-value="true"
 * data-datatable-buttons-value="true"  (enables export buttons)
 */
export default class extends Controller {
    static values = {
        pageLength: { type: Number, default: 10 },
        searching: { type: Boolean, default: true },
        ordering: { type: Boolean, default: true },
        info: { type: Boolean, default: true },
        paging: { type: Boolean, default: true },
        responsive: { type: Boolean, default: true },
        buttons: { type: Boolean, default: false },
        order: { type: Array, default: [[0, 'asc']] }
    }

    connect() {
        // Delay initialization slightly to ensure DOM is ready
        setTimeout(() => {
            try {
                // Check if DataTable already exists and destroy it
                if ($.fn.DataTable.isDataTable(this.element)) {
                    $(this.element).DataTable().destroy();
                }
                
                // Build configuration object
                const config = {
                    pageLength: this.pageLengthValue,
                    searching: this.searchingValue,
                    ordering: this.orderingValue,
                    info: this.infoValue,
                    paging: this.pagingValue,
                    responsive: this.responsiveValue,
                    order: this.orderValue,
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        },
                        emptyTable: "No data available in table",
                        zeroRecords: "No matching records found"
                    }
                };

                // Add buttons configuration if enabled
                if (this.buttonsValue) {
                    config.dom = 'Bfrtip';
                    config.buttons = [
                        'copy',
                        'csv',
                        'excel',
                        'pdf',
                        'print'
                    ];
                }
                
                // Initialize DataTable with configured options using jQuery
                this.dataTable = $(this.element).DataTable(config);
                
                console.log('DataTable initialized successfully with buttons:', this.buttonsValue);
            } catch (error) {
                console.error('DataTable initialization error:', error);
            }
        }, 100);
    }

    disconnect() {
        // Clean up DataTable instance when controller disconnects
        try {
            if (this.dataTable && $.fn.DataTable.isDataTable(this.element)) {
                this.dataTable.destroy();
                console.log('DataTable destroyed');
            }
        } catch (error) {
            console.error('DataTable disconnect error:', error);
        }
    }
}
