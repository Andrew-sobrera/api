import { Router } from 'express';
import authController from '../controllers/AuthController';


const router = new Router();

router.post('/', authController.login);

export default router;
