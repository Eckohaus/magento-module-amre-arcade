\# AMRE Arcade Terminal (Magento 2 Module)



\*\*Description:\*\* A relational interface layer for the Angular Momentum Reaction Engine (AMRE). This module facilitates 64-bit double-precision physics calculations by bridging the Magento 2 storefront with a self-hosted Fortran calculation motor.



\*\*URL:\*\* \[https://kupu-home.com/terminal/amre-arcade-terminal](https://kupu-home.com/terminal/amre-arcade-terminal)



\## 1. Technical Architecture

\- \*\*Protocol:\*\* Translates frontend JSON payloads into GET query parameters.

\- \*\*Endpoint:\*\* Proxies requests to the internal Nginx gateway (Port 8080).

\- \*\*Target:\*\* `base\_equation.bin` (Fortran Binary).



\## 2. Deployment Pipeline (Golden Rule)

1\. \*\*Draft/Test:\*\* Local OneDrive.

2\. \*\*Master Copy:\*\* GitHub (Push from local).

3\. \*\*Production Motor:\*\* Linode (Pull from GitHub).



\## 3. Administrative Note

This module utilizes inline CSS overrides (`!important`) in the `.phtml` templates to maintain the "Green Phosphor" UI across varied Magento theme states.

