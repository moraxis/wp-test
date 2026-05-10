const imageUpload = document.getElementById('imageUpload');
const gameCanvas = document.getElementById('gameCanvas');
const ctx = gameCanvas.getContext('2d');
const placeholderText = document.getElementById('placeholder-text');
const resetBtn = document.getElementById('resetBtn');
const flashOverlay = document.getElementById('flash-overlay');
const gameArea = document.getElementById('game-area');

let uploadedImage = null;
let currentHits = []; // Array to store {x, y, damageLevel}

// Damage Levels: 1 = Scratch, 2 = Bruise, 3 = Big Bruise
const MAX_DAMAGE_LEVEL = 3;
const HIT_RADIUS = 30; // Radius to group hits together to increase damage level

// SVG Assets as Data URIs for damage
// 1. Scratch (red marks)
const scratchSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><path d="M10 10 L40 40 M15 10 L45 40 M20 10 L50 40" stroke="red" stroke-width="3" stroke-linecap="round" opacity="0.7"/></svg>';
// 2. Bruise (purple/blue blob)
const bruiseSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><circle cx="25" cy="25" r="20" fill="purple" opacity="0.5" filter="blur(2px)"/></svg>';
// 3. Big Bruise (larger purple/blue/dark blob)
const bigBruiseSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 70 70"><circle cx="35" cy="35" r="30" fill="darkblue" opacity="0.6" filter="blur(3px)"/><circle cx="35" cy="35" r="20" fill="purple" opacity="0.7" filter="blur(2px)"/></svg>';

const damageImages = [];

function loadDamageImages() {
    const srcs = [scratchSrc, bruiseSrc, bigBruiseSrc];
    srcs.forEach((src, index) => {
        const img = new Image();
        img.onload = () => {
            damageImages[index + 1] = img;
        };
        img.src = src;
    });
}

loadDamageImages();

// Setup Audio Context for punch sound
let audioCtx;
function playPunchSound() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }

    // Resume context if suspended (browser auto-play policy)
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }

    const osc = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();

    osc.type = 'sine';

    // Create a "thud" sound by rapidly dropping frequency
    osc.frequency.setValueAtTime(150, audioCtx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);

    // Gain envelope for percussive hit
    gainNode.gain.setValueAtTime(1, audioCtx.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.1);

    osc.connect(gainNode);
    gainNode.connect(audioCtx.destination);

    osc.start();
    osc.stop(audioCtx.currentTime + 0.1);
}


// Handle Image Upload
imageUpload.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const img = new Image();
            img.onload = function() {
                uploadedImage = img;
                setupCanvas();
                resetGame();
            }
            img.src = event.target.result;
        }
        reader.readAsDataURL(file);
    }
});

// Resize canvas and fit image
function setupCanvas() {
    if (!uploadedImage) return;

    placeholderText.style.display = 'none';
    gameCanvas.style.display = 'block';
    resetBtn.disabled = false;

    // Get container dimensions
    const containerWidth = gameArea.clientWidth;
    const containerHeight = gameArea.clientHeight;

    // Calculate aspect ratio to fit image within container
    const imgRatio = uploadedImage.width / uploadedImage.height;
    const containerRatio = containerWidth / containerHeight;

    let drawWidth, drawHeight;

    if (imgRatio > containerRatio) {
        // Image is wider than container
        drawWidth = containerWidth;
        drawHeight = containerWidth / imgRatio;
    } else {
        // Image is taller than container
        drawHeight = containerHeight;
        drawWidth = containerHeight * imgRatio;
    }

    // Set canvas internal resolution to match displayed size for 1:1 coordinate mapping
    gameCanvas.width = drawWidth;
    gameCanvas.height = drawHeight;

    // Center canvas in container (CSS flex handles this, but just to be sure it takes correct space)
    gameCanvas.style.width = `${drawWidth}px`;
    gameCanvas.style.height = `${drawHeight}px`;

    drawScene();
}

// Redraw the base image and all hits
function drawScene() {
    if (!uploadedImage) return;

    ctx.clearRect(0, 0, gameCanvas.width, gameCanvas.height);
    ctx.drawImage(uploadedImage, 0, 0, gameCanvas.width, gameCanvas.height);

    // Draw hits
    currentHits.forEach(hit => {
        const dmgImg = damageImages[hit.damageLevel];
        if (dmgImg) {
            // Center the damage image on the hit coordinates
            // Assuming default damage image sizes are roughly 50x50, 50x50, 70x70
            let size = 50;
            if (hit.damageLevel === 3) size = 70;

            ctx.drawImage(dmgImg, hit.x - size/2, hit.y - size/2, size, size);
        }
    });
}

// Reset Game
function resetGame() {
    currentHits = [];
    drawScene();
}

resetBtn.addEventListener('click', resetGame);

// Handle window resize
window.addEventListener('resize', () => {
    if (uploadedImage) {
        setupCanvas();
    }
});


// Flash effect
function triggerFlash() {
    flashOverlay.classList.add('flash-active');
    setTimeout(() => {
        flashOverlay.classList.remove('flash-active');
    }, 50);
}


// Handle interaction (clicks/taps)
function handleHit(e) {
    if (!uploadedImage) return;

    e.preventDefault(); // Prevent default touch actions (scrolling, etc)

    let clientX, clientY;

    if (e.type === 'touchstart') {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    } else {
        clientX = e.clientX;
        clientY = e.clientY;
    }

    // Get coordinates relative to canvas
    const rect = gameCanvas.getBoundingClientRect();

    // Calculate scale in case CSS width/height differs from canvas internal width/height
    const scaleX = gameCanvas.width / rect.width;
    const scaleY = gameCanvas.height / rect.height;

    const x = (clientX - rect.left) * scaleX;
    const y = (clientY - rect.top) * scaleY;

    // Check if within canvas bounds
    if (x >= 0 && x <= gameCanvas.width && y >= 0 && y <= gameCanvas.height) {
        registerHit(x, y);
    }
}

function registerHit(x, y) {
    playPunchSound();
    triggerFlash();

    // Check if this hit is close to an existing hit to stack damage
    let foundExisting = false;
    for (let i = 0; i < currentHits.length; i++) {
        const hit = currentHits[i];
        const dist = Math.sqrt(Math.pow(hit.x - x, 2) + Math.pow(hit.y - y, 2));

        if (dist < HIT_RADIUS) {
            if (hit.damageLevel < MAX_DAMAGE_LEVEL) {
                hit.damageLevel++;
            }
            // Update the position slightly towards the new hit center
            hit.x = (hit.x + x) / 2;
            hit.y = (hit.y + y) / 2;
            foundExisting = true;
            break;
        }
    }

    if (!foundExisting) {
        // New hit
        currentHits.push({ x: x, y: y, damageLevel: 1 });
    }

    drawScene();
}

// Event listeners for hitting
gameCanvas.addEventListener('mousedown', handleHit);
gameCanvas.addEventListener('touchstart', handleHit, { passive: false });
