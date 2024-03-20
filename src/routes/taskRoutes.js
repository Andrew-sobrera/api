import { Router } from 'express';
import taskController from '../controllers/TaskController';

const router = new Router();

router.get('/', taskController.findAll);
router.post('/', taskController.create);

export default router;
