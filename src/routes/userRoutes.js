import { Router } from 'express';
import UserController from '../controllers/UserController';
import LoginRequired from '../middlewares/LoginRequired'

const router = new Router();

router.get('/', UserController.findAll);
router.get('/:id', UserController.show);
router.post('/', UserController.create);
router.put('/:id', UserController.update);
router.delete('/:id', UserController.destroy);

export default router;
