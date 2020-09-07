</div>
  <script src="inst.js" type="module" defer></script>
  <script type="text/javascript" defer>
  const images = document.querySelectorAll("img[data-src]");
  images.forEach(function(image) {
      image.addEventListener('click', e => {
          e.target.src = e.target.dataset.src;
          image.removeEventListener('click', e);
      });
  });
  </script>
<span><a class="excl" href="https://0xff.nu/saisho">最小</a></span>
</body>
</html>
