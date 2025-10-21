@component('mail::message')
# Hola {{ $user->name }}

Tu cuenta ha sido creada exitosamente en **Multimetales**.

**Usuario:** {{ $user->username }}  
**Correo:** {{ $user->email }}  
**Contraseña:** {{ $plainPassword }}

><p>Estas credenciales te servirán para iniciar sesión en:</p>
<p><a href="https://multimetales.com.mx">multimetales.com.mx</a></p>

Gracias,  
**Equipo Multimetales**
@endcomponent
