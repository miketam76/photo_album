</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- PhotoSwipe v5 (module) -->
  <link rel="stylesheet" href="https://unpkg.com/photoswipe@5/dist/photoswipe.css">
  <script type="module">
    import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5/dist/photoswipe-lightbox.esm.min.js';
    const lightbox = new PhotoSwipeLightbox({
      gallery: '#gallery',
      childSelector: 'a[data-pswp]'
    });
    lightbox.init();
  </script>
</body>
</html>
