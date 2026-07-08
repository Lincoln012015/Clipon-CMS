    <div id="statsModal" class="modal-overlay" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 id="modalTitle">Details</h3>
                <button class="btn-close" onclick="closeModal()">x</button>
            </div>
            <div class="modal-search">
                <input type="text" id="modalSearch" placeholder="Search..." oninput="filterModalTable()">
            </div>
            <div class="modal-body">
                <table class="modal-table">
                    <thead>
                        <tr id="modalTableHead"></tr>
                    </thead>
                    <tbody id="modalTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>