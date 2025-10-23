    <!-- Logout Modal -->
    <div class="modal" id="logoutModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
                <div class="modal-title">Confirm Logout</div>
            </div>
            <div class="modal-body">
                Are you sure you want to logout? You will be redirected to the login page.
            </div>
            <div class="modal-actions">
                <button class="btn btn-cancel" onclick="closeLogoutModal()">Cancel</button>
                <button class="btn btn-confirm" onclick="confirmLogout()">Logout</button>
            </div>
        </div>
    </div>
<!--javascript modal for logout-->
<script>
    // Logout Modal
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
            // Close dropdowns
            document.getElementById('profileDropdown').classList.remove('active');
            document.querySelector('.profile-header').classList.remove('active');
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) closeLogoutModal();
        });

        // Nav active state
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
                this.classList.add('active');
            });
        });

</script>