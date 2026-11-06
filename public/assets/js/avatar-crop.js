/**
 * Avatars are displayed inside circles (object-fit: cover), so a portrait or
 * landscape upload loses its sides on every screen that shows it. This helper
 * centre-crops the selected image to a square before it is uploaded, which
 * matches exactly what the circle would have shown.
 *
 * Bound by delegation so it keeps working after AJAX region swaps. Inputs opt
 * in with data-avatar-crop. If the crop fails the original file is uploaded
 * unchanged; the server keeps its own validation.
 */
(function () {
    'use strict';

    var MAX_EDGE = 512;
    var QUALITY = 0.9;

    // Files this helper produced, so the re-dispatched change event is not cropped again.
    var croppedFiles = new WeakSet();

    function loadImage(file) {
        if (typeof window.createImageBitmap === 'function') {
            return createImageBitmap(file, { imageOrientation: 'from-image' })
                .catch(function () { return loadImageViaUrl(file); });
        }
        return loadImageViaUrl(file);
    }

    function loadImageViaUrl(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var image = new Image();
            image.onload = function () {
                URL.revokeObjectURL(url);
                resolve(image);
            };
            image.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('The image could not be decoded.'));
            };
            image.src = url;
        });
    }

    function release(image) {
        if (typeof image.close === 'function') {
            image.close();
        }
    }

    function canvasToBlob(canvas) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                blob ? resolve(blob) : reject(new Error('The image could not be encoded.'));
            }, 'image/jpeg', QUALITY);
        });
    }

    function squareFileName(file) {
        var base = file.name.replace(/\.[^/.]+$/, '') || 'photo';
        return base + '.jpg';
    }

    async function toSquareFile(file) {
        var image = await loadImage(file);
        try {
            var side = Math.min(image.width, image.height);
            var edge = Math.min(MAX_EDGE, side);
            var canvas = document.createElement('canvas');

            canvas.width = edge;
            canvas.height = edge;

            var context = canvas.getContext('2d');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, edge, edge);
            context.imageSmoothingEnabled = true;
            context.imageSmoothingQuality = 'high';
            context.drawImage(
                image,
                (image.width - side) / 2,
                (image.height - side) / 2,
                side,
                side,
                0,
                0,
                edge,
                edge
            );
        } finally {
            release(image);
        }

        var blob = await canvasToBlob(canvas);
        var square = new File([blob], squareFileName(file), {
            type: 'image/jpeg',
            lastModified: file.lastModified
        });
        croppedFiles.add(square);
        return square;
    }

    async function replaceWithSquare(input, file) {
        var square = await toSquareFile(file);
        var transfer = new DataTransfer();
        transfer.items.add(square);
        input.files = transfer.files;
    }

    document.addEventListener('change', function (event) {
        var input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-avatar-crop')) {
            return;
        }

        var file = input.files && input.files[0];

        if (!file || croppedFiles.has(file) || file.type.indexOf('image/') !== 0) {
            return;
        }

        replaceWithSquare(input, file).then(
            function () {
                // Lets the view's own preview handler run against the cropped file.
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
            function () { /* keep the original upload */ }
        );
    });
})();
