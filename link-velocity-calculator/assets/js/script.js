document.addEventListener('DOMContentLoaded', function() {
    var calculateBtn = document.getElementById('lvc-calculate-btn');

    if (!calculateBtn) return; // Exit if not on the calculator page

    calculateBtn.addEventListener('click', function(e) {
        e.preventDefault();

        var myLinksInput = document.getElementById('lvc-my-links').value;
        var compLinksInput = document.getElementById('lvc-competitor-links').value;
        var compGrowthInput = document.getElementById('lvc-competitor-growth').value;
        var monthsInput = document.getElementById('lvc-months').value;

        // Parse inputs, defaulting to 0 if empty or invalid
        var myLinks = parseInt(myLinksInput, 10) || 0;
        var compLinks = parseInt(compLinksInput, 10) || 0;
        var compGrowth = parseInt(compGrowthInput, 10) || 0;
        var months = parseInt(monthsInput, 10);

        // Validation
        if (isNaN(months) || months <= 0) {
            alert('Please enter a valid number of months (greater than 0).');
            return;
        }

        // Formula: (Competitor Links + (Competitor Growth * Months) - My Links) / Months
        var requiredLinks = (compLinks + (compGrowth * months) - myLinks) / months;

        // If required links is negative, you're already ahead or catching up naturally
        if (requiredLinks < 0) {
            requiredLinks = 0;
        }

        // Round up to nearest whole number because you can't build a fraction of a link
        requiredLinks = Math.ceil(requiredLinks);

        // Display results
        var resultContainer = document.getElementById('lvc-result-container');
        var resultNumber = document.getElementById('lvc-result-number');

        resultNumber.textContent = requiredLinks;

        // Show container if hidden
        resultContainer.classList.remove('lvc-result-hidden');
    });
});
