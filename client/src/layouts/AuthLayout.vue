<template>
    <div class="auth-container">
      <div class="container-fluid h-100">
        <div class="row h-100">
          <div class="col-lg-6 d-flex justify-content-center align-items-center form-column">
            <div class="w-100" style="max-width: 450px;">
              <slot />
            </div>
          </div>
  
          <div class="col-lg-6 d-none d-lg-block p-0 image-column">
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
    overflow: hidden; 
    background-color: var(--bs-light);
  }
  
  .form-column {
    padding: 2rem;
  }
  
  .image-column {
    height: 100vh;
  }
  
  .image-column .img-fluid {
    width: 100%;
    height: 100%;
    object-fit: cover; 
  }
  </style>