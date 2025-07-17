<template>
  <div class="auth-container">
    <div class="container-fluid h-100 p-0">
      <div class="row h-100 g-0">
        <div class="col-lg-6 d-flex flex-column justify-content-center align-items-center form-column">
          <div class="form-wrapper">
            <slot />
          </div>
        </div>

        <div class="col-lg-6 d-none d-lg-block image-column">
          <img 
            src="https://images.pexels.com/photos/5409751/pexels-photo-5409751.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" 
            alt="Immagine descrittiva" 
            class="img-fluid"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-container {
  height: 100vh;
  width: 100vw;
  background-color: var(--bs-light);

}


.form-column {
  height: 100vh;      
  overflow-y: auto;   
  padding: 2rem 1rem; 
}


.form-wrapper {
  width: 100%;
  max-width: 500px; 
}


.image-column {
  height: 100vh;
  overflow: hidden;
}

.image-column .img-fluid {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>