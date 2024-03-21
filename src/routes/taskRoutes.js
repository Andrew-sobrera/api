import { Router } from 'express';
import taskController from '../controllers/TaskController';

const router = new Router();

router.get('/', taskController.findAll);
router.get('/:task', taskController.show)
router.post('/', taskController.create);
router.delete('/:task', taskController.destroy);
router.put('/:task', taskController.update);


export default router;
