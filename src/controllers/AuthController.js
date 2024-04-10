import User from '../models/User';
import bcryptjs from 'bcryptjs';

class AuthController{
    
    async login(req, res){
       try {
            const { email, password } = req.body
            
            const user = await User.findOne({
                where: {
                    email
                }
            })
        
            const isValid = await bcryptjs.compare(password, user.password_hash);

            if(!isValid){
                res.status(401).send('usuario inválido')
            }

            const token = user.token

            res.send({user, token})
       } catch (error) {

            throw new error(error)
        
       }
    }
}

export default new AuthController();